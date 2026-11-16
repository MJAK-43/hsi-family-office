<?php

namespace App\Services;

use App\Enums\FundMovementStatus;
use App\Enums\FundMovementType;
use App\Models\ApprovalRequest;
use App\Models\FundAccount;
use App\Models\FundMovement;
use App\Support\FundCurrency;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Diagramme de flux financiers (Sankey) : catégories d'entrées → comptes du fonds → catégories de sorties.
 * Les virements internes relient les comptes entre eux ; l'écart entre entrées et sorties d'un compte
 * apparaît comme « Solde conservé » (ou « Solde antérieur utilisé » quand le compte a dépensé plus qu'il n'a reçu).
 */
class FundFlowService
{
    /** Couleurs des familles de nœuds (palette HSI). */
    private const COLORS = ['in' => '#2F9E6B', 'account' => '#0B2748', 'out' => '#C2410C', 'project' => '#C2410C', 'pending' => '#CBD5E1', 'balance' => '#94A3B8', 'reserve' => '#D4AF37'];

    /** Projets « validés » rattachés à un compte : à financer, financés en cours, terminés. */
    public const PROJECT_STAGES = [
        'approved_by_family' => 'pending',
        'funded' => 'in_progress',
        'in_progress' => 'in_progress',
        'completed' => 'completed',
    ];

    public static function period(?string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'year' => [$today->startOfYear(), $today->endOfDay(), 'year'],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear(), 'last_year'],
            'quarter' => [$today->startOfQuarter(), $today->endOfDay(), 'quarter'],
            '12m' => [$today->subMonthsNoOverflow(11)->startOfMonth(), $today->endOfDay(), '12m'],
            'custom' => [
                $from ? CarbonImmutable::parse($from)->startOfDay() : $today->subMonthsNoOverflow(11)->startOfMonth(),
                $to ? CarbonImmutable::parse($to)->endOfDay() : $today->endOfDay(),
                'custom',
            ],
            default => [
                CarbonImmutable::parse(FundMovement::query()->min('movement_date') ?? $today)->startOfMonth(),
                $today->endOfDay(), 'all',
            ],
        };
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, fund_account_id?: ?int, movements?: Collection}  $f
     */
    public function sankey(array $f): array
    {
        $accountId = $f['fund_account_id'] ?? null;
        $detail = (bool) ($f['detail_projects'] ?? false);
        $movements = $f['movements'] ?? FundMovement::query()->with(['account:id,name', 'relatedAccount:id,name', 'project.type', 'contributionPayment:id,contribution_plan_id,payment_period_label'])
            ->where('status', FundMovementStatus::Validated)
            ->whereDate('movement_date', '>=', $f['from']->toDateString())
            ->whereDate('movement_date', '<=', $f['to']->toDateString())
            ->when($accountId, fn ($q) => $q->where('fund_account_id', $accountId))
            ->get();

        $approvalTypes = $this->approvalTypeNames($movements);
        $nodes = [];
        $links = [];
        $node = function (string $id, string $label, string $kind, array $extra = []) use (&$nodes) {
            $nodes[$id] ??= ['id' => $id, 'label' => $label, 'kind' => $kind, 'color' => self::COLORS[$kind], ...$extra];

            return $id;
        };
        $link = function (string $source, string $target, float $value) use (&$links) {
            if ($value <= 0.004 || $source === $target) return;
            $key = $source.'>'.$target;
            $links[$key] = ['source' => $source, 'target' => $target, 'value' => round(($links[$key]['value'] ?? 0) + $value, 2)];
        };

        $inByAccount = [];
        $outByAccount = [];
        $transfers = [];

        foreach ($movements as $m) {
            $amount = (float) $m->amount;
            $account = $node('acc:'.$m->fund_account_id, $m->account?->name ?? 'Compte', 'account');
            $isTransfer = in_array($m->type, [FundMovementType::TransferIn, FundMovementType::TransferOut], true);

            if ($isTransfer && ! $accountId) {
                // Vue globale : les virements relient deux comptes (comptés une seule fois, côté sortie).
                if ($m->direction === 'debit' && $m->related_fund_account_id) {
                    $node('acc:'.$m->related_fund_account_id, $m->relatedAccount?->name ?? 'Compte', 'account');
                    $pair = $m->fund_account_id.'>'.$m->related_fund_account_id;
                    $transfers[$pair] = ($transfers[$pair] ?? 0) + $amount;
                }

                continue;
            }

            if ($detail && $m->type === FundMovementType::ProjectFunding && $m->project_id && $m->direction === 'debit') {
                // Vue détaillée : chaque projet financé devient un flux à part entière.
                $link($account, $node('proj:'.$m->project_id, $m->project?->title ?? 'Projet', 'project', [
                    'stage' => self::PROJECT_STAGES[$m->project?->status?->value] ?? 'in_progress', 'project_id' => $m->project_id,
                ]), $amount);
                $outByAccount[$m->fund_account_id] = ($outByAccount[$m->fund_account_id] ?? 0) + $amount;

                continue;
            }

            [$key, $label] = $this->category($m, $approvalTypes);
            if ($m->direction === 'credit') {
                $link($node('in:'.$key, $label, 'in'), $account, $amount);
                $inByAccount[$m->fund_account_id] = ($inByAccount[$m->fund_account_id] ?? 0) + $amount;
            } else {
                $link($account, $node('out:'.$key, $label, 'out'), $amount);
                $outByAccount[$m->fund_account_id] = ($outByAccount[$m->fund_account_id] ?? 0) + $amount;
            }
        }

        // Virements nets entre comptes, sans boucle (un Sankey ne peut pas représenter de cycle).
        foreach ($this->acyclicTransfers($transfers) as [$from, $to, $value]) {
            $link('acc:'.$from, 'acc:'.$to, $value);
            $outByAccount[$from] = ($outByAccount[$from] ?? 0) + $value;
            $inByAccount[$to] = ($inByAccount[$to] ?? 0) + $value;
        }

        // Équilibre de chaque compte sur la période.
        foreach (array_keys($nodes) as $id) {
            if (! str_starts_with($id, 'acc:')) continue;
            $accId = (int) substr($id, 4);
            $net = round(($inByAccount[$accId] ?? 0) - ($outByAccount[$accId] ?? 0), 2);
            if ($net > 0) $link($id, $node('balance:kept', 'Solde conservé', 'balance'), $net);
            if ($net < 0) $link($node('reserve:used', 'Solde antérieur utilisé', 'reserve'), $id, -$net);
        }

        // Projets validés pas encore financés : flux « à verser » (prévus, hors totaux et hors solde).
        $projects = $detail && $accountId ? $this->accountProjects($accountId) : [];
        foreach ($projects as $project) {
            if ($project['stage'] !== 'pending' || $project['requested_raw'] <= 0) continue;
            $account = $node('acc:'.$accountId, FundAccount::find($accountId)?->name ?? 'Compte', 'account');
            $link($account, $node('pending:'.$project['id'], $project['title'], 'pending', ['stage' => 'pending', 'project_id' => $project['id']]), $project['requested_raw']);
        }

        $usedIds = collect($links)->flatMap(fn ($l) => [$l['source'], $l['target']])->unique()->flip();
        $totalIn = round(collect($links)->filter(fn ($l) => str_starts_with($l['source'], 'in:'))->sum('value'), 2);
        $totalOut = round(collect($links)->filter(fn ($l) => str_starts_with($l['target'], 'out:') || str_starts_with($l['target'], 'proj:'))->sum('value'), 2);
        $planned = round(collect($links)->filter(fn ($l) => str_starts_with($l['target'], 'pending:'))->sum('value'), 2);

        return [
            'nodes' => array_values(array_filter($nodes, fn ($n) => $usedIds->has($n['id']))),
            'links' => array_values($links),
            'totals' => ['in' => $totalIn, 'out' => $totalOut, 'net' => round($totalIn - $totalOut, 2), 'planned' => $planned],
            'projects' => $projects,
            'breakdown' => [
                'in' => $this->breakdown($links, 'in:', $nodes, 'source'),
                'out' => array_values(collect([...$this->breakdown($links, 'out:', $nodes, 'target'), ...$this->breakdown($links, 'proj:', $nodes, 'target')])->sortByDesc('total')->all()),
            ],
            'period' => ['from' => $f['from']->format('d/m/Y'), 'to' => $f['to']->format('d/m/Y'), 'from_raw' => $f['from']->toDateString(), 'to_raw' => $f['to']->toDateString()],
            'currency' => FundCurrency::shared(),
            'account' => $accountId ? FundAccount::find($accountId)?->name : null,
        ];
    }

    /** Catégorie lisible d'un mouvement (entrée ou sortie). */
    private function category(FundMovement $m, array $approvalTypes): array
    {
        $type = $m->type;
        $credit = $m->direction === 'credit';

        // Données d'exemple : clairement identifiées dans le diagramme.
        if ($m->is_example && $m->type === FundMovementType::ManualIncome && ! isset($approvalTypes[$m->id])) {
            return ['examples', 'Apports fictifs (exemple)'];
        }

        return match ($type) {
            FundMovementType::ContributionAllocation => $m->contributionPayment && ! $m->contributionPayment->contribution_plan_id
                ? ['voluntary', 'Contributions volontaires']
                : ['contributions', 'Cotisations'],
            FundMovementType::PenaltyIncome => $m->project_id
                ? ['loan_penalties', 'Pénalités de remboursement']
                : ['contribution_penalties', 'Pénalités de cotisation'],
            FundMovementType::LoanRepayment => ['loan_repayments', 'Prêts remboursés (capital)'],
            FundMovementType::LoanInterest => ['loan_interest', 'Intérêts des prêts'],
            FundMovementType::ProjectFunding => $m->project?->type?->is_loan
                ? ['loans', 'Prêts versés']
                : ['projects', 'Financement de projets'],
            FundMovementType::WithdrawalRefund => ['withdrawals', 'Remboursements aux membres sortants'],
            FundMovementType::TransferIn => ['transfer_in:'.$m->related_fund_account_id, 'Virement depuis '.($m->relatedAccount?->name ?? 'un autre compte')],
            FundMovementType::TransferOut => ['transfer_out:'.$m->related_fund_account_id, 'Virement vers '.($m->relatedAccount?->name ?? 'un autre compte')],
            FundMovementType::Adjustment => [$credit ? 'adjustment_plus' : 'adjustment_minus', $credit ? 'Ajustements (+)' : 'Ajustements (−)'],
            FundMovementType::ManualIncome, FundMovementType::ManualExpense => isset($approvalTypes[$m->id])
                ? ['tx:'.$approvalTypes[$m->id], $approvalTypes[$m->id]]
                : [$credit ? 'manual_in' : 'manual_out', $credit ? 'Entrées manuelles' : 'Dépenses manuelles'],
        };
    }

    /** Nom du type de transaction (frais bancaires, dons…) des mouvements créés par le circuit de validation. */
    private function approvalTypeNames(Collection $movements): array
    {
        $manual = $movements->filter(fn ($m) => in_array($m->type, [FundMovementType::ManualIncome, FundMovementType::ManualExpense], true));
        if ($manual->isEmpty()) return [];
        $ids = $manual->pluck('id')->all();
        $names = [];
        ApprovalRequest::query()->with('type:id,name')->where('status', 'executed')->whereNotNull('result')->get(['id', 'transaction_type_id', 'result'])
            ->each(function (ApprovalRequest $r) use (&$names, $ids) {
                $movementId = $r->result['movement_id'] ?? null;
                if ($movementId && in_array($movementId, $ids, true) && $r->type) $names[$movementId] = $r->type->name;
            });

        return $names;
    }

    /** Virements nets par paire de comptes ; les plus gros d'abord, ceux qui fermeraient une boucle sont ignorés. */
    private function acyclicTransfers(array $transfers): array
    {
        $net = [];
        foreach ($transfers as $pair => $value) {
            [$a, $b] = array_map('intval', explode('>', $pair));
            $reverse = $transfers["{$b}>{$a}"] ?? 0;
            if ($value > $reverse) $net[] = [$a, $b, round($value - $reverse, 2)];
        }
        usort($net, fn ($x, $y) => $y[2] <=> $x[2]);
        $edges = [];
        $kept = [];
        $reaches = function (int $start, int $goal) use (&$edges): bool {
            $stack = [$start];
            $seen = [];
            while ($stack) {
                $n = array_pop($stack);
                if ($n === $goal) return true;
                if (isset($seen[$n])) continue;
                $seen[$n] = true;
                foreach ($edges[$n] ?? [] as $next) $stack[] = $next;
            }

            return false;
        };
        foreach ($net as [$a, $b, $value]) {
            if ($reaches($b, $a)) continue;
            $edges[$a][] = $b;
            $kept[] = [$a, $b, $value];
        }

        return $kept;
    }

    private function breakdown(array $links, string $prefix, array $nodes, string $side): array
    {
        return collect($links)->filter(fn ($l) => str_starts_with($l[$side], $prefix))
            ->groupBy($side)->map(fn ($items, $id) => ['label' => $nodes[$id]['label'], 'total' => round($items->sum('value'), 2)])
            ->sortByDesc('total')->values()->all();
    }

    /** Projets validés rattachés au compte, avec ce qu'ils ont reçu (et remboursé pour les prêts). */
    public function accountProjects(int $accountId): array
    {
        $projects = \App\Models\Project::query()->with(['owner:id,name', 'type:id,name,is_loan'])
            ->where('fund_account_id', $accountId)->whereIn('status', array_keys(self::PROJECT_STAGES))
            ->orderByDesc('decided_at')->orderByDesc('id')->get();
        if ($projects->isEmpty()) return [];
        $funded = FundMovement::query()->where('status', FundMovementStatus::Validated)->where('type', FundMovementType::ProjectFunding)
            ->whereIn('project_id', $projects->pluck('id'))->selectRaw('project_id, SUM(amount) as total, MIN(movement_date) as first_date')->groupBy('project_id')->get()->keyBy('project_id');
        $repaid = FundMovement::query()->where('status', FundMovementStatus::Validated)->whereIn('type', [FundMovementType::LoanRepayment, FundMovementType::LoanInterest, FundMovementType::PenaltyIncome])
            ->whereIn('project_id', $projects->pluck('id'))->selectRaw('project_id, SUM(amount) as total')->groupBy('project_id')->pluck('total', 'project_id');

        return $projects->map(function (\App\Models\Project $p) use ($funded, $repaid) {
            $received = (float) ($funded[$p->id]->total ?? 0);
            $stage = self::PROJECT_STAGES[$p->status->value] ?? 'pending';
            if ($stage === 'pending' && $received > 0) $stage = 'in_progress';

            return [
                'id' => $p->id, 'title' => $p->title, 'owner' => $p->owner?->name, 'type' => $p->type?->name, 'is_loan' => (bool) $p->type?->is_loan,
                'status' => $p->status->value, 'status_label' => $p->status->label(), 'status_variant' => $p->status->color(), 'stage' => $stage,
                'requested' => FundCurrency::format($p->requested_amount), 'requested_raw' => (float) $p->requested_amount,
                'received' => FundCurrency::format($received), 'received_raw' => $received,
                'repaid' => isset($repaid[$p->id]) ? FundCurrency::format($repaid[$p->id]) : null,
                'funded_on' => isset($funded[$p->id]) ? CarbonImmutable::parse($funded[$p->id]->first_date)->format('d/m/Y') : null,
                'is_example' => (bool) $p->is_example,
                'url' => route('projects.show', $p),
            ];
        })->values()->all();
    }
}
