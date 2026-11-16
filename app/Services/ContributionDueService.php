<?php

namespace App\Services;

use App\Enums\ContributionDueStatus;
use App\Enums\ContributionFrequency;
use App\Enums\MemberStatus;
use App\Models\ContributionDue;
use App\Models\ContributionPayment;
use App\Models\ContributionPlan;
use App\Models\User;
use App\Notifications\ContributionDueReminder;
use App\Support\ContributionRules;
use App\Support\FundRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ContributionDueService
{
    public function generate(?CarbonImmutable $date = null): int
    {
        $date ??= CarbonImmutable::today();
        $created = 0;
        $plans = ContributionPlan::query()->where('is_active', true)->where('auto_generate', true)->get();
        $members = User::query()->with('memberProfile')->whereHas('memberProfile', fn ($q) => $q->where('status', MemberStatus::Active))->get();

        foreach ($plans as $plan) {
            [$periodKey, $periodLabel, $periodStart] = $this->period($plan, $date);
            if ($plan->starts_on && $periodStart->lt($plan->starts_on->startOfDay())) continue;
            $dueDate = $periodStart->day(min((int) $plan->due_day, $periodStart->daysInMonth));
            foreach ($members as $member) {
                if ($plan->country_id && $member->memberProfile?->country_id !== $plan->country_id) continue;
                $due = ContributionDue::firstOrCreate([
                    'contribution_plan_id' => $plan->id, 'user_id' => $member->id, 'period_key' => $periodKey,
                ], [
                    'period_label' => $periodLabel, 'amount' => $plan->amount, 'currency' => $plan->currency,
                    'due_date' => $dueDate, 'status' => ContributionDueStatus::Pending,
                ]);
                if ($due->wasRecentlyCreated) {
                    $created++;
                    $this->carryPenalties($due);
                }
            }
            $plan->update(['last_generated_at' => now()]);
        }
        return $created;
    }

    public function markLateAndRemind(?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::today(); $late = 0; $reminded = 0;
        ContributionDue::query()->with(['plan', 'user'])->whereIn('status', [ContributionDueStatus::Pending, ContributionDueStatus::Late])->each(function (ContributionDue $due) use ($date, &$late, &$reminded) {
            $lateDate = $due->due_date->addDays((int) $due->plan->grace_days);
            if ($date->gt($lateDate) && $due->status !== ContributionDueStatus::Late) {
                $due->update(['status' => ContributionDueStatus::Late]); $late++;
            }
            if ($date->gte($due->due_date) && ! $due->reminded_at) {
                $due->user->notify(new ContributionDueReminder($due));
                $due->update(['reminded_at' => now()]); $reminded++;
            }
        });
        return compact('late', 'reminded');
    }

    /**
     * Part du paiement qui règle des pénalités (pénalité de l'échéance, figée à la date de réception, et pénalités reportées).
     * Sert à inscrire cette part comme « pénalité de cotisation » dans le registre du fonds.
     */
    public function penaltyPortion(ContributionPayment $payment): float
    {
        $due = $this->findDueFor($payment);
        if (! $due) return 0.0;
        $receivedOn = CarbonImmutable::parse($payment->paid_at ?? $payment->validated_at ?? now());
        $penalties = ($due->penalty_waived ? 0.0 : $this->penaltyFor($due, $receivedOn)) + (float) $due->carried_penalty;
        $extra = (float) $payment->amount - (float) $due->amount;

        return round(max(0.0, min($extra, $penalties)), 2);
    }

    /** Échéance que ce paiement vient régler (sans la modifier). */
    public function findDueFor(ContributionPayment $payment): ?ContributionDue
    {
        if (! $payment->contribution_plan_id) return null;
        $plan = $payment->plan;
        if ($plan?->is_regulatory && $payment->payment_month && $payment->payment_year && $payment->user) {
            $due = $this->ensureQuarterDue($payment->user, (int) $payment->payment_year, ContributionRules::quarterOfMonth((int) $payment->payment_month));
            if (in_array($due->status, [ContributionDueStatus::Pending, ContributionDueStatus::Late], true)) return $due;
        }

        return ContributionDue::query()->where('contribution_plan_id', $payment->contribution_plan_id)
            ->where('user_id', $payment->user_id)->whereIn('status', [ContributionDueStatus::Pending, ContributionDueStatus::Late])
            ->when($payment->payment_year, fn ($q) => $q->where('period_key', 'like', $payment->payment_year.'%'))
            ->oldest('due_date')->first();
    }

    public function markPaid(ContributionPayment $payment): ?ContributionDue
    {
        if (! $payment->contribution_plan_id) return null;
        return DB::transaction(function () use ($payment) {
            $plan = $payment->plan;
            $due = null;
            // Contribution trimestrielle du règlement : l'échéance du trimestre déclaré (créée au besoin).
            if ($plan?->is_regulatory && $payment->payment_month && $payment->payment_year) {
                $quarter = ContributionRules::quarterOfMonth((int) $payment->payment_month);
                $due = $this->ensureQuarterDue($payment->user, (int) $payment->payment_year, $quarter);
                if (! in_array($due->status, [ContributionDueStatus::Pending, ContributionDueStatus::Late], true)) $due = null;
                else $due = ContributionDue::query()->lockForUpdate()->find($due->id);
            }
            $due ??= ContributionDue::query()->where('contribution_plan_id', $payment->contribution_plan_id)
                ->where('user_id', $payment->user_id)->whereIn('status', [ContributionDueStatus::Pending, ContributionDueStatus::Late])
                ->when($payment->payment_year, fn ($q) => $q->where('period_key', 'like', $payment->payment_year.'%'))
                ->oldest('due_date')->lockForUpdate()->first();
            if ($due) {
                // Pénalité figée à la date de réception du paiement, puis reportée sur l'échéance suivante.
                $receivedOn = CarbonImmutable::parse($payment->paid_at ?? $payment->validated_at ?? now());
                $due->update([
                    'status' => ContributionDueStatus::Paid, 'contribution_payment_id' => $payment->id, 'paid_at' => $payment->validated_at ?? now(),
                    'penalty_amount' => $due->penalty_waived ? $due->penalty_amount : $this->penaltyFor($due, $receivedOn),
                ]);
                $next = ContributionDue::query()->where('user_id', $due->user_id)->whereKeyNot($due->id)
                    ->whereIn('status', [ContributionDueStatus::Pending, ContributionDueStatus::Late])
                    ->whereDate('due_date', '>', $due->due_date->toDateString())->oldest('due_date')->first();
                if ($next) $this->carryPenalties($next);
            }
            return $due;
        });
    }

    /** Échéance d'un trimestre pour un membre (contribution du règlement), créée si elle n'existe pas encore. */
    public function ensureQuarterDue(User $user, int $year, int $quarter): ContributionDue
    {
        $plan = ContributionRules::plan();
        $due = ContributionDue::firstOrCreate([
            'contribution_plan_id' => $plan->id, 'user_id' => $user->id, 'period_key' => ContributionRules::periodKey($year, $quarter),
        ], [
            'period_label' => ContributionRules::periodLabel($year, $quarter), 'amount' => $plan->amount, 'currency' => $plan->currency,
            'due_date' => ContributionRules::dueDate($year, $quarter), 'status' => ContributionDueStatus::Pending,
        ]);
        if ($due->wasRecentlyCreated) $this->carryPenalties($due);

        return $due;
    }

    /**
     * Section 4.3 : 2 $ (paramétrable) par période de 7 jours entamée à partir du 16 du mois.
     */
    public function penaltyFor(ContributionDue $due, CarbonImmutable $date): float
    {
        $amount = FundRules::get('contribution_penalty_amount');
        $period = max(1, FundRules::get('contribution_penalty_period_days'));
        $dueDate = CarbonImmutable::parse($due->due_date)->startOfDay();
        $start = $dueDate->day(min(FundRules::get('contribution_penalty_start_day'), $dueDate->daysInMonth));
        if ($start->lte($dueDate)) $start = $dueDate->addDay();
        $date = $date->startOfDay();
        if ($date->lt($start)) return 0.0;

        return round(((int) floor($start->diffInDays($date) / $period) + 1) * $amount, 2);
    }

    /** Recalcule chaque jour la pénalité des échéances encore impayées. */
    public function applyPenalties(?CarbonImmutable $date = null): int
    {
        $date ??= CarbonImmutable::today();
        $updated = 0;
        ContributionDue::query()->whereIn('status', [ContributionDueStatus::Pending, ContributionDueStatus::Late])
            ->where('penalty_waived', false)->each(function (ContributionDue $due) use ($date, &$updated) {
                $penalty = $this->penaltyFor($due, $date);
                if (abs($penalty - (float) $due->penalty_amount) > 0.001) {
                    $due->update(['penalty_amount' => $penalty]);
                    $updated++;
                }
            });

        return $updated;
    }

    /** Ajoute à cette échéance les pénalités déjà figées (échéances payées) qui n'ont pas encore été reportées. */
    public function carryPenalties(ContributionDue $target): void
    {
        $previous = ContributionDue::query()->where('user_id', $target->user_id)->whereKeyNot($target->id)
            ->where('status', ContributionDueStatus::Paid)->where('penalty_waived', false)
            ->whereNull('penalty_carried_to_id')->where('penalty_amount', '>', 0)->get();
        if ($previous->isEmpty()) return;
        $target->update(['carried_penalty' => round((float) $target->carried_penalty + $previous->sum(fn ($d) => (float) $d->penalty_amount), 2)]);
        ContributionDue::whereIn('id', $previous->pluck('id'))->update(['penalty_carried_to_id' => $target->id]);
    }

    public function waivePenalty(ContributionDue $due, User $user, string $reason): ContributionDue
    {
        $due->update(['penalty_waived' => true, 'penalty_waiver_reason' => $reason, 'penalty_waived_by' => $user->id]);
        // Si la pénalité avait déjà été reportée, on la retire de l'échéance qui la portait.
        if ($due->penalty_carried_to_id && $carrier = ContributionDue::find($due->penalty_carried_to_id)) {
            $carrier->update(['carried_penalty' => max(0, round((float) $carrier->carried_penalty - (float) $due->penalty_amount, 2))]);
        }
        activity('contributions')->performedOn($due)->causedBy($user)->event('contribution.penalty.waived')
            ->withProperties(['penalty' => (float) $due->penalty_amount, 'reason' => $reason])->log('Pénalité de retard annulée (erreur bancaire justifiée)');

        return $due->fresh();
    }

    private function period(ContributionPlan $plan, CarbonImmutable $date): array
    {
        return match ($plan->frequency) {
            ContributionFrequency::Monthly => [$date->format('Y-m'), ucfirst($date->locale('fr')->translatedFormat('F Y')), $date->startOfMonth()],
            ContributionFrequency::Quarterly => [$date->format('Y').'-Q'.$date->quarter, 'T'.$date->quarter.' '.$date->year, $date->startOfQuarter()],
            ContributionFrequency::Yearly => [$date->format('Y'), 'Année '.$date->year, $date->startOfYear()],
            ContributionFrequency::OneTime => ['once', 'Cotisation ponctuelle', CarbonImmutable::parse($plan->starts_on ?? $plan->created_at)->startOfDay()],
        };
    }
}
