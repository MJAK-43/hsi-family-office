<?php

namespace App\Support;

use App\Enums\ContributionFrequency;
use App\Models\ContributionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Section 4 du règlement : contribution trimestrielle fixe, reçue par le trésorier de zone
 * au plus tard le 15 du premier mois du trimestre, confirmée sous 2 jours ouvrés.
 */
class ContributionRules
{
    public const PLAN_NAME = 'Contribution trimestrielle';

    /** Plan unique qui porte la contribution obligatoire ; créé ou mis à jour d'après les paramètres du fonds. */
    public static function ensurePlan(): ?ContributionPlan
    {
        if (! Schema::hasColumn('contribution_plans', 'is_regulatory')) return null;
        $values = [
            'amount' => (float) FundRules::get('contribution_quarterly_amount'),
            'currency' => FundCurrency::code(),
            'frequency' => ContributionFrequency::Quarterly->value,
            'due_day' => (int) FundRules::get('contribution_due_day'),
            'grace_days' => 0,
            'auto_generate' => true,
            'is_default' => true,
            'is_active' => true,
        ];
        $id = DB::table('contribution_plans')->where('is_regulatory', true)->whereNull('deleted_at')->value('id');
        if ($id) {
            DB::table('contribution_plans')->where('id', $id)->update([...$values, 'updated_at' => now()]);
        } else {
            $id = DB::table('contribution_plans')->insertGetId([...$values,
                'name' => self::PLAN_NAME, 'description' => 'Section 4 du règlement intérieur : contribution fixe tous les 3 mois, avant le 15 du premier mois du trimestre.',
                'is_regulatory' => true, 'starts_on' => FundRules::get('first_payment_date') ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ContributionPlan::find($id);
    }

    public static function plan(): ?ContributionPlan
    {
        return ContributionPlan::query()->where('is_regulatory', true)->first() ?? self::ensurePlan();
    }

    public static function periodKey(int $year, int $quarter): string
    {
        return "{$year}-Q{$quarter}";
    }

    public static function periodLabel(int $year, int $quarter): string
    {
        return "T{$quarter} {$year}";
    }

    public static function firstMonth(int $quarter): int
    {
        return ($quarter - 1) * 3 + 1;
    }

    public static function quarterOfMonth(?int $month): ?int
    {
        return $month ? intdiv($month - 1, 3) + 1 : null;
    }

    /** Date limite : le 15 (paramétrable) du premier mois du trimestre. */
    public static function dueDate(int $year, int $quarter): CarbonImmutable
    {
        $start = CarbonImmutable::create($year, self::firstMonth($quarter), 1);

        return $start->day(min((int) FundRules::get('contribution_due_day'), $start->daysInMonth));
    }

    /** Le trésorier confirme la réception sous N jours ouvrés (section 4.2). */
    public static function confirmationDeadline(CarbonImmutable $submittedAt): CarbonImmutable
    {
        $date = $submittedAt->startOfDay();
        $days = max(1, (int) FundRules::get('contribution_confirmation_days'));
        while ($days > 0) {
            $date = $date->addDay();
            if (! $date->isWeekend()) $days--;
        }

        return $date->endOfDay();
    }

    /** Trésoriers de la zone du membre ; à défaut, tous les trésoriers. */
    public static function zoneTreasurers(User $member): Collection
    {
        $zone = $member->memberProfile?->zone;
        $treasurers = $zone ? $zone->treasurers()->get() : collect();

        return $treasurers->isNotEmpty() ? $treasurers : Notifier::treasurers();
    }

    /** Paramètres utiles au formulaire (calcul de la pénalité et du montant attendu). */
    public static function forForm(): array
    {
        return [
            'quarterly_amount' => (float) FundRules::get('contribution_quarterly_amount'),
            'due_day' => (int) FundRules::get('contribution_due_day'),
            'penalty_amount' => (float) FundRules::get('contribution_penalty_amount'),
            'penalty_start_day' => (int) FundRules::get('contribution_penalty_start_day'),
            'penalty_period_days' => max(1, (int) FundRules::get('contribution_penalty_period_days')),
            'confirmation_days' => (int) FundRules::get('contribution_confirmation_days'),
        ];
    }
}
