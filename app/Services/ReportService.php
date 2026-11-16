<?php

namespace App\Services;

use App\Enums\ContributionDueStatus;
use App\Enums\ContributionPaymentStatus;
use App\Enums\FundMovementStatus;
use App\Enums\FundMovementType;
use App\Enums\MemberStatus;
use App\Enums\ProjectStatus;
use App\Models\ContributionDue;
use App\Models\ContributionPayment;
use App\Models\FundAccount;
use App\Models\FundMovement;
use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rapports du fonds (section 6) : indicateurs, évolutions, registre des membres, état des prêts
 * et relevés de compte, filtrables par période, zone, type de projet et membre.
 */
class ReportService
{
    public function __construct(private EligibilityService $eligibility) {}

    public function normalizeFilters(array $input): array
    {
        $to = ! empty($input['to']) ? CarbonImmutable::parse($input['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $from = ! empty($input['from']) ? CarbonImmutable::parse($input['from'])->startOfDay() : $to->subMonthsNoOverflow(11)->startOfMonth();
        if ($from->gt($to)) [$from, $to] = [$to->startOfDay(), $from->endOfDay()];

        return [
            'from' => $from, 'to' => $to,
            'zone_id' => ! empty($input['zone_id']) ? (int) $input['zone_id'] : null,
            'project_type_id' => ! empty($input['project_type_id']) ? (int) $input['project_type_id'] : null,
            'user_id' => ! empty($input['user_id']) ? (int) $input['user_id'] : null,
            'fund_account_id' => ! empty($input['fund_account_id']) ? (int) $input['fund_account_id'] : null,
            'movement_type' => $input['movement_type'] ?? null,
        ];
    }

    private function scopeMember(Builder $query, array $f, string $column = 'user_id'): Builder
    {
        return $query
            ->when($f['user_id'], fn ($q) => $q->where($column, $f['user_id']))
            ->when($f['zone_id'], fn ($q) => $q->whereIn($column, User::whereHas('memberProfile', fn ($p) => $p->where('zone_id', $f['zone_id']))->select('id')));
    }

    private function projectsQuery(array $f): Builder
    {
        return $this->scopeMember(Project::query(), $f, 'owner_id')
            ->when($f['project_type_id'], fn ($q) => $q->where('project_type_id', $f['project_type_id']));
    }

    private function paymentsQuery(array $f): Builder
    {
        return $this->scopeMember(ContributionPayment::query()->where('status', ContributionPaymentStatus::Validated), $f)
            ->whereBetween(DB::raw('COALESCE(paid_at, validated_at)'), [$f['from']->toDateTimeString(), $f['to']->toDateTimeString()]);
    }

    public function kpis(array $f): array
    {
        $loans = $this->projectsQuery($f)->with('installments')->whereHas('type', fn ($q) => $q->where('is_loan', true))
            ->where('status', ProjectStatus::InProgress)->get();
        $installmentsInRange = LoanInstallment::query()->whereDate('due_date', '>=', $f['from']->toDateString())->whereDate('due_date', '<=', $f['to']->toDateString())
            ->whereIn('project_id', $this->projectsQuery($f)->select('id'));
        $duesInRange = $this->scopeMember(ContributionDue::query(), $f)->whereDate('due_date', '>=', $f['from']->toDateString())->whereDate('due_date', '<=', $f['to']->toDateString());

        return [
            'contributions' => round((float) $this->paymentsQuery($f)->sum('amount'), 2),
            'contributions_count' => $this->paymentsQuery($f)->count(),
            'contribution_penalties' => round((float) (clone $duesInRange)->where('penalty_waived', false)->sum('penalty_amount'), 2),
            'loan_penalties' => round((float) (clone $installmentsInRange)->sum('penalty_amount'), 2),
            'late_contributions' => (clone $duesInRange)->where('status', ContributionDueStatus::Late)->count(),
            'late_installments' => (clone $installmentsInRange)->where('status', 'late')->count(),
            'balance' => $this->eligibility->fundBalance(),
            'disbursed' => round((float) $this->movementsQuery($f)->where('type', FundMovementType::ProjectFunding)->sum('amount'), 2),
            'repayments' => round((float) LoanRepayment::query()->whereDate('paid_on', '>=', $f['from']->toDateString())->whereDate('paid_on', '<=', $f['to']->toDateString())
                ->when($f['user_id'] || $f['zone_id'] || $f['project_type_id'], fn ($q) => $q->whereIn('project_id', $this->projectsQuery($f)->select('id')))->sum('amount'), 2),
            'outstanding' => round($loans->sum(fn ($loan) => $loan->installments->sum(fn ($i) => $i->remaining() + $i->remainingPenalty())), 2),
            'active_loans' => $loans->count(),
            'active_members' => $this->scopeMember(User::query()->whereHas('memberProfile', fn ($q) => $q->where('status', MemberStatus::Active)), $f, 'id')->count(),
        ];
    }

    private function movementsQuery(array $f): Builder
    {
        return FundMovement::query()->where('status', FundMovementStatus::Validated)
            ->whereDate('movement_date', '>=', $f['from']->toDateString())->whereDate('movement_date', '<=', $f['to']->toDateString())
            ->when($f['fund_account_id'], fn ($q) => $q->where('fund_account_id', $f['fund_account_id']))
            ->when($f['movement_type'], fn ($q) => $q->where('type', $f['movement_type']))
            ->when($f['user_id'] || $f['zone_id'] || $f['project_type_id'], fn ($q) => $q->where(function ($sub) use ($f) {
                $sub->whereIn('project_id', $this->projectsQuery($f)->select('id'))
                    ->orWhereIn('contribution_payment_id', $this->scopeMember(ContributionPayment::query(), $f)->select('id'));
            }));
    }

    /** Évolution mois par mois : cotisations, décaissements, remboursements. */
    public function monthlySeries(array $f): array
    {
        $months = [];
        $cursor = $f['from']->startOfMonth();
        $limit = 0;
        while ($cursor->lte($f['to']) && $limit++ < 36) {
            $months[$cursor->format('Y-m')] = ['label' => $cursor->format('m/Y'), 'contributions' => 0.0, 'disbursements' => 0.0, 'repayments' => 0.0];
            $cursor = $cursor->addMonthNoOverflow();
        }
        foreach ($this->paymentsQuery($f)->get(['amount', 'paid_at', 'validated_at']) as $p) {
            $key = CarbonImmutable::parse($p->paid_at ?? $p->validated_at)->format('Y-m');
            if (isset($months[$key])) $months[$key]['contributions'] += (float) $p->amount;
        }
        // Remboursements reçus : capital, intérêts et pénalités de retard des prêts.
        foreach ($this->movementsQuery($f)->whereIn('type', [FundMovementType::ProjectFunding, FundMovementType::LoanRepayment, FundMovementType::LoanInterest, FundMovementType::PenaltyIncome])->get(['amount', 'type', 'movement_date', 'project_id']) as $m) {
            $key = CarbonImmutable::parse($m->movement_date)->format('Y-m');
            if (! isset($months[$key])) continue;
            if ($m->type === FundMovementType::PenaltyIncome && ! $m->project_id) continue;
            $months[$key][$m->type === FundMovementType::ProjectFunding ? 'disbursements' : 'repayments'] += (float) $m->amount;
        }

        return array_values(array_map(fn ($m) => [...$m, 'contributions' => round($m['contributions'], 2), 'disbursements' => round($m['disbursements'], 2), 'repayments' => round($m['repayments'], 2)], $months));
    }

    public function movementsByType(array $f): array
    {
        return $this->movementsQuery($f)->get(['type', 'amount', 'direction'])->groupBy(fn ($m) => $m->type->value)
            ->map(fn ($items, $type) => ['type' => $type, 'label' => FundMovementType::from($type)->label(), 'total' => round($items->sum(fn ($m) => (float) $m->amount), 2), 'count' => $items->count()])
            ->sortByDesc('total')->values()->all();
    }

    public function projectsBreakdown(array $f): array
    {
        $projects = $this->projectsQuery($f)->with('type')->whereBetween('created_at', [$f['from'], $f['to']])->get();

        return [
            'by_status' => $projects->groupBy(fn ($p) => $p->status->value)->map(fn ($items, $status) => ['label' => ProjectStatus::from($status)->label(), 'count' => $items->count()])->values()->all(),
            'by_type' => $projects->groupBy(fn ($p) => $p->type?->name ?? 'Sans type')->map(fn ($items, $name) => ['label' => $name, 'count' => $items->count(), 'requested' => round($items->sum(fn ($p) => (float) $p->requested_amount), 2)])->values()->all(),
        ];
    }

    /** Registre des membres (section 2.2) : cotisations, part du fonds, prêts et pénalités. */
    public function membersRegister(array $f): array
    {
        $allContributions = $this->eligibility->totalContributions();
        $users = $this->scopeMember(User::query()->whereHas('memberProfile')->with('memberProfile.zone'), $f, 'id')->orderBy('name')->get();
        $totals = ContributionPayment::query()->where('status', ContributionPaymentStatus::Validated)
            ->selectRaw('user_id, SUM(amount) as total, COUNT(*) as count, MAX(COALESCE(paid_at, validated_at)) as last_at')->groupBy('user_id')->get()->keyBy('user_id');

        return $users->map(function (User $user) use ($totals, $allContributions) {
            $total = (float) ($totals[$user->id]->total ?? 0);
            $loans = $this->eligibility->ongoingLoans($user);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'zone' => $user->memberProfile?->zone?->name ?? '—',
                'status' => $user->memberProfile?->status?->label() ?? '—',
                'contributions' => round($total, 2),
                'contributions_count' => (int) ($totals[$user->id]->count ?? 0),
                'last_contribution' => isset($totals[$user->id]->last_at) ? CarbonImmutable::parse($totals[$user->id]->last_at)->format('d/m/Y') : null,
                'share_percent' => $allContributions > 0 ? round($total / $allContributions * 100, 2) : 0,
                'loans_outstanding' => round($loans->sum(fn ($loan) => $loan->installments->sum(fn ($i) => $i->remaining())), 2),
                'penalties' => round($this->eligibility->contributionPenaltiesDue($user) + $this->eligibility->loanPenaltiesDue($user), 2),
            ];
        })->all();
    }

    public function loans(array $f): array
    {
        return $this->projectsQuery($f)->with(['owner', 'installments', 'repayments', 'type'])
            ->whereHas('type', fn ($q) => $q->where('is_loan', true))
            ->whereIn('status', [ProjectStatus::InProgress, ProjectStatus::Completed])
            ->orderByDesc('funded_at')->get()->map(function (Project $loan) {
                $next = $loan->installments->first(fn ($i) => $i->remaining() > 0);

                return [
                    'id' => $loan->id, 'title' => $loan->title, 'borrower' => $loan->owner?->name,
                    'amount' => (float) $loan->funded_amount, 'total_due' => (float) $loan->loan_total_due,
                    'paid' => round((float) $loan->repayments->sum('amount'), 2),
                    'remaining' => round($loan->installments->sum(fn ($i) => $i->remaining()), 2),
                    'penalties' => round($loan->installments->sum(fn ($i) => $i->remainingPenalty()), 2),
                    'next_due' => $next?->due_date?->format('d/m/Y'),
                    'late' => $loan->installments->where('status', 'late')->count(),
                    'status' => $loan->repaid_at ? 'Soldé' : ($loan->installments->contains('status', 'late') ? 'En retard' : 'À jour'),
                    'funded_at' => $loan->funded_at?->format('d/m/Y'),
                ];
            })->all();
    }

    /** Relevé de compte : solde d'ouverture, mouvements de la période, solde de clôture. */
    public function statement(array $f): array
    {
        $base = FundMovement::query()->where('status', FundMovementStatus::Validated)
            ->when($f['fund_account_id'], fn ($q) => $q->where('fund_account_id', $f['fund_account_id']));
        $signed = fn ($m) => $m->direction === 'credit' ? (float) $m->amount : -(float) $m->amount;
        $opening = round((clone $base)->whereDate('movement_date', '<', $f['from']->toDateString())->get(['amount', 'direction'])->sum($signed), 2);
        $running = $opening;
        $rows = $this->movementsQuery($f)->with('account')->orderBy('movement_date')->orderBy('id')->get()->map(function (FundMovement $m) use (&$running, $signed) {
            $running = round($running + $signed($m), 2);

            return [
                'date' => CarbonImmutable::parse($m->movement_date)->format('d/m/Y'),
                'account' => $m->account?->name,
                'type' => $m->type->label(),
                'label' => $m->label,
                'reference' => $m->reference,
                'credit' => $m->direction === 'credit' ? (float) $m->amount : null,
                'debit' => $m->direction === 'debit' ? (float) $m->amount : null,
                'balance' => $running,
            ];
        })->all();

        return [
            'opening' => $opening,
            'closing' => $running,
            'credits' => round(collect($rows)->sum('credit'), 2),
            'debits' => round(collect($rows)->sum('debit'), 2),
            'rows' => $rows,
            'account' => $f['fund_account_id'] ? FundAccount::find($f['fund_account_id'])?->name : 'Tous les comptes',
        ];
    }

    public function accounts(): array
    {
        return FundAccount::query()->orderBy('name')->get()->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'balance' => (float) $a->current_balance, 'is_active' => $a->is_active])->all();
    }

    /** Tout le contenu d'un rapport, utilisé pour l'écran, les exports et les rapports partagés. */
    public function full(array $f): array
    {
        return [
            'period' => ['from' => $f['from']->format('d/m/Y'), 'to' => $f['to']->format('d/m/Y')],
            'kpis' => $this->kpis($f),
            'monthly' => $this->monthlySeries($f),
            'movements_by_type' => $this->movementsByType($f),
            'projects' => $this->projectsBreakdown($f),
            'members' => $this->membersRegister($f),
            'loans' => $this->loans($f),
            'accounts' => $this->accounts(),
            'statement' => $this->statement($f),
            'flows' => $this->flows($f),
        ];
    }

    /** Diagramme des flux (entrées → comptes → sorties) avec les filtres du rapport. */
    public function flows(array $f): array
    {
        return app(\App\Services\FundFlowService::class)->sankey([
            'from' => $f['from'], 'to' => $f['to'], 'fund_account_id' => $f['fund_account_id'],
            'movements' => $this->movementsQuery($f)->with(['account:id,name', 'relatedAccount:id,name', 'project.type'])->get(),
        ]);
    }
}
