<?php

namespace App\Services;

use App\Enums\ContributionDueStatus;
use App\Enums\ContributionPaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\ContributionDue;
use App\Models\ContributionPayment;
use App\Models\FundAccount;
use App\Models\LoanInstallment;
use App\Models\Project;
use App\Models\User;
use App\Support\FundRules;
use App\Support\ProjectFormatter;
use Carbon\CarbonImmutable;

/**
 * Situation d'un membre au regard du règlement (fiche du demandeur) et vérifications
 * automatiques des critères d'un dossier : ancienneté, cotisations, prêts en cours, plafond…
 */
class EligibilityService
{
    /** Statuts d'un prêt pas encore soldé (versé, en remboursement). */
    public const ONGOING_LOAN_STATUSES = [ProjectStatus::Funded, ProjectStatus::InProgress];

    /** Statuts d'une demande encore en cours d'instruction. */
    public const PIPELINE_STATUSES = [ProjectStatus::Submitted, ProjectStatus::UnderReview, ProjectStatus::NeedsInformation, ProjectStatus::ApprovedByCommittee, ProjectStatus::SubmittedToVote, ProjectStatus::ApprovedByFamily];

    public function fundBalance(): float
    {
        return round((float) FundAccount::query()->where('is_active', true)->sum('current_balance'), 2);
    }

    public function fundCap(): float
    {
        return round($this->fundBalance() * FundRules::get('loan_max_balance_percent') / 100, 2);
    }

    public function totalContributions(?User $user = null): float
    {
        return round((float) ContributionPayment::query()
            ->where('status', ContributionPaymentStatus::Validated)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->sum('amount'), 2);
    }

    /** Part du membre dans le fonds : % du total des cotisations validées. */
    public function sharePercent(User $user): float
    {
        $all = $this->totalContributions();

        return $all > 0 ? round($this->totalContributions($user) / $all * 100, 2) : 0.0;
    }

    public function contributionPenaltiesDue(User $user): float
    {
        $dues = ContributionDue::query()->where('user_id', $user->id)->where('penalty_waived', false)->get();
        $open = $dues->whereIn('status', [ContributionDueStatus::Pending, ContributionDueStatus::Late]);

        // Pénalités des échéances encore ouvertes + pénalités reportées sur une échéance ouverte.
        return round($open->sum(fn ($due) => (float) $due->carried_penalty)
            + $dues->filter(fn ($due) => $due->penalty_carried_to_id === null && ! in_array($due->status, [ContributionDueStatus::Waived], true))
                ->sum(fn ($due) => (float) $due->penalty_amount), 2);
    }

    public function loanPenaltiesDue(User $user): float
    {
        return round(LoanInstallment::query()
            ->whereHas('project', fn ($q) => $q->where('owner_id', $user->id))
            ->get()->sum(fn (LoanInstallment $installment) => $installment->remainingPenalty()), 2);
    }

    /** Prêts versés et pas encore totalement remboursés. */
    public function ongoingLoans(User $user, ?Project $except = null)
    {
        return Project::query()->with('type', 'installments')
            ->where('owner_id', $user->id)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->whereHas('type', fn ($q) => $q->where('is_loan', true))
            ->whereIn('status', self::ONGOING_LOAN_STATUSES)
            ->whereNull('repaid_at')
            ->get();
    }

    public function situation(User $user, ?Project $project = null): array
    {
        $user->loadMissing('memberProfile.zone', 'memberProfile.country');
        $profile = $user->memberProfile;
        $payments = ContributionPayment::query()->where('user_id', $user->id)
            ->where('status', ContributionPaymentStatus::Validated)->get(['amount', 'paid_at', 'validated_at']);
        $first = $payments->map(fn ($p) => $p->paid_at ?? $p->validated_at)->filter()->min();
        $months = $first ? (int) floor(CarbonImmutable::parse($first)->diffInMonths(now())) : 0;
        $lateDues = ContributionDue::query()->where('user_id', $user->id)->where('status', ContributionDueStatus::Late)->count();
        $loans = $this->ongoingLoans($user, $project);
        $outstanding = $loans->sum(fn (Project $loan) => $loan->installments->sum(fn ($i) => $i->remaining()));
        $pipeline = Project::query()->where('owner_id', $user->id)
            ->when($project, fn ($q) => $q->whereKeyNot($project->id))
            ->whereIn('status', self::PIPELINE_STATUSES)->count();
        $lastRefusal = $this->lastRefusal($user, $project);

        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatarUrl(),
            'status' => $profile?->status?->label() ?? '—',
            'status_value' => $profile?->status?->value,
            'zone' => $profile?->zone?->name,
            'country' => $profile?->country?->name,
            'member_since' => ($profile?->member_since ?? $user->created_at)?->format('d/m/Y'),
            'total_contributions' => ProjectFormatter::money($this->totalContributions($user)),
            'contributions_count' => $payments->count(),
            'first_contribution' => $first ? CarbonImmutable::parse($first)->format('d/m/Y') : null,
            'contribution_months' => $months,
            'share_percent' => $this->sharePercent($user),
            'late_dues' => $lateDues,
            'contribution_penalties' => ProjectFormatter::money($this->contributionPenaltiesDue($user)),
            'loan_penalties' => ProjectFormatter::money($this->loanPenaltiesDue($user)),
            'ongoing_loans' => $loans->count(),
            'outstanding_loans' => ProjectFormatter::money($outstanding),
            'other_requests' => $pipeline,
            'last_refusal' => $lastRefusal ? [
                'title' => $lastRefusal->title,
                'date' => $this->refusalDate($lastRefusal)?->format('d/m/Y'),
            ] : null,
            'fund_balance' => ProjectFormatter::money($this->fundBalance()),
            'fund_cap' => ProjectFormatter::money($this->fundCap()),
            'fund_cap_percent' => FundRules::get('loan_max_balance_percent'),
        ];
    }

    /** Résultat de chaque vérification automatique : ['result' => pass|fail|na, 'detail' => …]. */
    public function checks(Project $project): array
    {
        $project->loadMissing('type', 'owner');
        $user = $project->owner;
        $isLoan = (bool) $project->type?->is_loan;
        $results = [];

        $opensOn = FundRules::get('loans_open_on');
        $results['loans_open'] = ! $isLoan ? $this->na() : (
            ! $opensOn || now()->startOfDay()->gte(CarbonImmutable::parse($opensOn))
                ? $this->pass('Prêts ouverts depuis le '.CarbonImmutable::parse($opensOn ?: now())->format('d/m/Y'))
                : $this->fail('Les prêts ouvrent le '.CarbonImmutable::parse($opensOn)->format('d/m/Y'))
        );

        $situation = $this->situation($user, $project);
        $results['contributing_member'] = $situation['status_value'] === 'active' && $situation['contributions_count'] > 0
            ? $this->pass($situation['contributions_count'].' cotisation(s) validée(s)')
            : $this->fail($situation['contributions_count'] === 0 ? 'Aucune cotisation validée' : 'Le membre n’est pas actif');

        $minMonths = FundRules::get('loan_min_contribution_months');
        $minPayments = (int) ceil($minMonths / 3);
        $results['seniority'] = $situation['contribution_months'] >= $minMonths && $situation['contributions_count'] >= $minPayments
            ? $this->pass($situation['contribution_months'].' mois de cotisation')
            : $this->fail($situation['contribution_months'].' mois de cotisation, '.$situation['contributions_count'].' versement(s) (minimum '.$minMonths.' mois et '.$minPayments.' versements)');

        $results['contributions_up_to_date'] = $situation['late_dues'] === 0
            ? $this->pass('Aucune échéance en retard')
            : $this->fail($situation['late_dues'].' échéance(s) de cotisation en retard');

        $results['no_ongoing_loan'] = $situation['ongoing_loans'] === 0
            ? $this->pass('Aucun prêt en cours')
            : $this->fail($situation['ongoing_loans'].' prêt(s) en cours, reste dû '.$situation['outstanding_loans']);

        $penalties = $this->contributionPenaltiesDue($user) + $this->loanPenaltiesDue($user);
        $results['no_pending_penalties'] = $penalties <= 0
            ? $this->pass('Aucune pénalité impayée')
            : $this->fail('Pénalités impayées : '.ProjectFormatter::money($penalties));

        $cap = $this->fundCap();
        $requested = (float) $project->requested_amount;
        $results['amount_within_fund_cap'] = $requested <= $cap
            ? $this->pass('Demandé '.ProjectFormatter::money($requested).' ≤ plafond '.ProjectFormatter::money($cap))
            : $this->fail('Demandé '.ProjectFormatter::money($requested).' > plafond '.ProjectFormatter::money($cap).' ('.FundRules::get('loan_max_balance_percent').' % du solde)');

        $maxDuration = FundRules::get('loan_max_duration_months');
        $results['loan_duration'] = ! $isLoan ? $this->na() : (
            $project->loan_duration_months && $project->loan_duration_months <= $maxDuration
                ? $this->pass($project->loan_duration_months.' mois (maximum '.$maxDuration.')')
                : $this->fail(($project->loan_duration_months ? $project->loan_duration_months.' mois' : 'Durée non renseignée').' (maximum '.$maxDuration.' mois)')
        );

        $results['refusal_delay'] = $this->refusalCheck($project);

        $min = FundRules::get('project_notice_min_days');
        $max = FundRules::get('project_notice_max_days');
        if (! $isLoan) {
            $results['notice_period'] = $this->na();
        } elseif (! $project->presentation_date) {
            $results['notice_period'] = $this->fail('Date de présentation non renseignée');
        } else {
            $from = CarbonImmutable::parse($project->submitted_at ?? now())->startOfDay();
            $days = (int) $from->diffInDays(CarbonImmutable::parse($project->presentation_date)->startOfDay(), false);
            $results['notice_period'] = $days >= $min && $days <= $max
                ? $this->pass('Présentation le '.$project->presentation_date->format('d/m/Y').', '.$days.' jours après le dépôt')
                : $this->fail('Présentation '.$days.' jours après le dépôt (attendu entre '.$min.' et '.$max.' jours)');
        }

        $results['personal_contribution'] = ! $project->type?->requires_personal_contribution ? $this->na() : (
            $project->respectsFiftyFiftyRule()
                ? $this->pass('Apport personnel '.$project->personalContributionPercent().' % du budget')
                : $this->fail('Apport personnel '.$project->personalContributionPercent().' % du budget (minimum 50 %)')
        );

        return $results;
    }

    /** Problèmes qui empêchent de déposer la demande. */
    public function submissionBlockers(Project $project): array
    {
        $checks = $this->checks($project);
        $blocking = ['refusal_delay'];
        if ($project->type?->is_loan) {
            $blocking = [...$blocking, 'loans_open', 'no_ongoing_loan', 'loan_duration'];
        }

        return collect($blocking)->filter(fn ($key) => ($checks[$key]['result'] ?? 'na') === 'fail')
            ->map(fn ($key) => $checks[$key]['detail'])->values()->all();
    }

    private function refusalCheck(Project $project): array
    {
        $sameMonths = FundRules::get('refusal_wait_same_project_months');
        $otherDays = FundRules::get('refusal_wait_other_project_days');
        $today = now()->startOfDay();

        if ($project->resubmission_of_id) {
            $previous = Project::withTrashed()->find($project->resubmission_of_id);
            $date = $previous ? $this->refusalDate($previous) : null;
            if ($date && in_array($previous->status, [ProjectStatus::RejectedByCommittee, ProjectStatus::RejectedByFamily], true)) {
                $allowed = CarbonImmutable::parse($date)->addMonths($sameMonths)->startOfDay();

                return $today->gte($allowed)
                    ? $this->pass('Même projet refusé le '.$date->format('d/m/Y').' : délai de '.$sameMonths.' mois respecté')
                    : $this->fail('Même projet refusé le '.$date->format('d/m/Y').' : nouveau dépôt possible à partir du '.$allowed->format('d/m/Y'));
            }
        }

        $last = $this->lastRefusal($project->owner, $project);
        if (! $last) {
            return $this->pass('Aucun refus récent');
        }
        $date = $this->refusalDate($last);
        $allowed = CarbonImmutable::parse($date)->addDays($otherDays)->startOfDay();

        return $today->gte($allowed)
            ? $this->pass('Dernier refus le '.$date->format('d/m/Y').' : délai de '.$otherDays.' jours respecté')
            : $this->fail('Dernier refus le '.$date->format('d/m/Y').' : nouveau projet possible à partir du '.$allowed->format('d/m/Y'));
    }

    private function lastRefusal(User $user, ?Project $except = null): ?Project
    {
        return Project::query()->where('owner_id', $user->id)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->whereIn('status', [ProjectStatus::RejectedByCommittee, ProjectStatus::RejectedByFamily])
            ->get()->sortByDesc(fn (Project $p) => $this->refusalDate($p))->first();
    }

    private function refusalDate(Project $project): ?\Carbon\CarbonInterface
    {
        return $project->decided_at ?? $project->reviewed_at ?? $project->updated_at;
    }

    private function pass(string $detail): array
    {
        return ['result' => 'pass', 'detail' => $detail];
    }

    private function fail(string $detail): array
    {
        return ['result' => 'fail', 'detail' => $detail];
    }

    private function na(): array
    {
        return ['result' => 'na', 'detail' => 'Ne s’applique pas à ce type de projet'];
    }
}
