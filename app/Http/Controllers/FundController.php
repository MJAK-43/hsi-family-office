<?php

namespace App\Http\Controllers;

use App\Enums\FundMovementStatus;
use App\Enums\FundMovementType;
use App\Http\Requests\CancelFundMovementRequest;
use App\Http\Requests\StoreFundMovementRequest;
use App\Http\Requests\TransferFundRequest;
use App\Models\FundAccount;
use App\Models\FundMovement;
use App\Services\FundService;
use App\Support\FundFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class FundController extends Controller
{
    public function __construct(private FundService $fundService) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view funds'), 403);

        $accounts = FundAccount::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $totalBalance = (float) $accounts->sum('current_balance');
        $treasury = $accounts->firstWhere('slug', 'treasury');
        $allocatedTotal = (float) FundMovement::validated()
            ->where('type', FundMovementType::ContributionAllocation)
            ->sum('amount');
        $movementsThisMonth = FundMovement::validated()
            ->whereMonth('movement_date', now()->month)
            ->whereYear('movement_date', now()->year)
            ->count();
        $topAccount = $accounts->sortByDesc('current_balance')->first();

        $recentMovements = $request->user()->can('view fund movements')
            ? FundMovement::with(['account', 'creator'])
                ->latest('movement_date')
                ->latest('created_at')
                ->limit(100)
                ->get()
                ->map(fn (FundMovement $m) => $this->formatMovement($m))
            : collect();

        [$flowFrom, $flowTo, $flowPreset] = \App\Services\FundFlowService::period($request->query('flow_period'), $request->query('flow_from'), $request->query('flow_to'));

        return Inertia::render('Funds/Index', [
            'flow' => fn () => app(\App\Services\FundFlowService::class)->sankey(['from' => $flowFrom, 'to' => $flowTo]),
            'flowPeriod' => $flowPreset,
            // Une carte de flux par compte, avec le détail des projets qu'il finance.
            'accountFlows' => fn () => $accounts->map(fn (FundAccount $a) => [
                'account' => $this->formatAccount($a, $totalBalance, detailed: true),
                'flow' => app(\App\Services\FundFlowService::class)->sankey(['from' => $flowFrom, 'to' => $flowTo, 'fund_account_id' => $a->id, 'detail_projects' => true]),
            ])->values(),
            'inactiveAccounts' => FundAccount::where('is_active', false)->orderBy('name')->get()->map(fn (FundAccount $a) => $this->formatAccount($a, $totalBalance, detailed: true)),
            'examples' => ['projects' => \App\Models\Project::where('is_example', true)->count()],
            'resetPhrase' => self::RESET_PHRASE,
            'stats' => [
                [
                    'title' => 'Total du fonds',
                    'value' => FundFormatter::money($totalBalance),
                    'change' => count($accounts).' poches',
                    'changeType' => 'success',
                ],
                [
                    'title' => 'Fonds disponible',
                    'value' => FundFormatter::money($treasury?->current_balance ?? 0),
                    'change' => 'Trésorerie',
                    'changeType' => 'neutral',
                ],
                [
                    'title' => 'Cotisations allouées',
                    'value' => FundFormatter::money($allocatedTotal),
                    'change' => 'Depuis le début',
                    'changeType' => 'success',
                ],
                [
                    'title' => 'Mouvements ce mois',
                    'value' => (string) $movementsThisMonth,
                    'change' => now()->translatedFormat('F Y'),
                    'changeType' => 'neutral',
                ],
                [
                    'title' => 'Poche principale',
                    'value' => $topAccount?->name ?? '—',
                    'change' => $topAccount ? FundFormatter::money($topAccount->current_balance) : '—',
                    'changeType' => 'gold',
                ],
            ],
            'accounts' => $accounts->map(fn (FundAccount $a) => $this->formatAccount($a, $totalBalance, detailed: true)),
            'allocationChart' => $this->allocationChartData($accounts, $totalBalance),
            'evolutionChart' => $this->evolutionChartData(),
            'recentMovements' => $recentMovements,
            'can' => $this->permissions($request->user()),
        ]);
    }

    public function show(FundAccount $fundAccount): Response
    {
        abort_unless(auth()->user()->can('view funds'), 403);

        $fundAccount->load(['movements' => fn ($q) => $q->with(['creator', 'relatedAccount'])->latest()->limit(300)]);

        $totalBalance = (float) FundAccount::where('is_active', true)->sum('current_balance');

        $credits = (float) $fundAccount->movements()->validated()->where('direction', 'credit')->sum('amount');
        $debits = (float) $fundAccount->movements()->validated()->where('direction', 'debit')->sum('amount');

        $request = request();
        [$flowFrom, $flowTo, $flowPreset] = \App\Services\FundFlowService::period($request->query('flow_period'), $request->query('flow_from'), $request->query('flow_to'));

        return Inertia::render('Funds/Show', [
            'flow' => fn () => app(\App\Services\FundFlowService::class)->sankey(['from' => $flowFrom, 'to' => $flowTo, 'fund_account_id' => $fundAccount->id, 'detail_projects' => true]),
            'flowPeriod' => $flowPreset,
            'account' => $this->formatAccount($fundAccount, $totalBalance, detailed: true),
            'movements' => $fundAccount->movements->map(fn (FundMovement $m) => $this->formatMovement($m)),
            'stats' => [
                'credits' => FundFormatter::money($credits),
                'debits' => FundFormatter::money($debits),
                'movements_count' => $fundAccount->movements()->count(),
            ],
            'can' => $this->permissions(auth()->user()),
        ]);
    }

    public function movements(Request $request): Response
    {
        abort_unless($request->user()->can('view fund movements'), 403);

        $query = FundMovement::with(['account', 'creator', 'relatedAccount'])
            ->when($request->fund_account_id, fn ($q, $id) => $q->where('fund_account_id', $id))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->direction, fn ($q, $dir) => $q->where('direction', $dir))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('movement_date', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('movement_date', '<=', $date))
            ->latest('movement_date')
            ->latest('created_at');

        $movements = $query->paginate($request->integer('per_page', 15))->withQueryString();
        $movements->getCollection()->transform(fn (FundMovement $m) => $this->formatMovement($m));

        return Inertia::render('Funds/Movements', [
            'movements' => $movements,
            'filters' => $request->only(['fund_account_id', 'type', 'direction', 'status', 'date_from', 'date_to', 'per_page']),
            'accounts' => FundAccount::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'types' => FundMovementType::options(),
            'statuses' => FundMovementStatus::options(),
            'can' => $this->permissions($request->user()),
        ]);
    }

    public function createMovement(): Response
    {
        abort_unless(auth()->user()->can('create fund movements'), 403);

        return Inertia::render('Funds/CreateMovement', [
            'accounts' => FundAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'currency', 'current_balance']),
            'types' => collect(FundMovementType::options())->filter(fn ($t) => in_array($t['value'], [
                FundMovementType::ManualIncome->value,
                FundMovementType::ManualExpense->value,
                FundMovementType::Adjustment->value,
            ]))->values(),
        ]);
    }

    public function storeMovement(StoreFundMovementRequest $request): RedirectResponse
    {
        // Les sorties et entrées manuelles suivent le nombre de validations de leur type de transaction.
        $code = $request->direction === 'debit' ? 'manual_expense' : 'bank_income';
        $approvals = app(\App\Services\ApprovalService::class);
        if ($approvals->requiredApprovals($code) > 1) {
            try {
                $result = $approvals->request($code, $request->label, $request->user(), (float) $request->amount, [
                    'fund_account_id' => (int) $request->fund_account_id, 'description' => $request->description,
                    'reference' => $request->reference, 'movement_date' => $request->movement_date ?? now()->toDateString(),
                ]);
            } catch (RuntimeException|InvalidArgumentException $e) {
                return back()->withErrors(['amount' => $e->getMessage()])->withInput();
            }

            return redirect()->route('approvals.index')->with('success', $result->status === 'executed'
                ? 'Mouvement enregistré avec succès.'
                : 'Mouvement soumis à validation : il sera enregistré après les validations requises.');
        }

        try {
            $this->fundService->createMovement([
                'fund_account_id' => $request->fund_account_id,
                'type' => $request->type,
                'amount' => $request->amount,
                'currency' => strtoupper($request->currency),
                'direction' => $request->direction,
                'label' => $request->label,
                'description' => $request->description,
                'reference' => $request->reference,
                'movement_date' => $request->movement_date ?? now()->toDateString(),
                'created_by' => $request->user()->id,
                'validated_by' => $request->user()->id,
            ]);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()->route('funds.movements')
            ->with('success', 'Mouvement enregistré avec succès.');
    }

    public function transferForm(): Response
    {
        abort_unless(auth()->user()->can('transfer funds'), 403);

        return Inertia::render('Funds/Transfer', [
            'accounts' => FundAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'currency', 'current_balance']),
        ]);
    }

    public function transfer(TransferFundRequest $request): RedirectResponse
    {
        $approvals = app(\App\Services\ApprovalService::class);
        if ($approvals->requiredApprovals('internal_transfer') > 1) {
            try {
                $result = $approvals->request('internal_transfer', 'Virement interne — '.$request->description, $request->user(), (float) $request->input('amount'), [
                    'from_fund_account_id' => (int) $request->from_fund_account_id, 'to_fund_account_id' => (int) $request->to_fund_account_id,
                    'description' => $request->description,
                ]);
            } catch (RuntimeException|InvalidArgumentException $e) {
                return back()->withErrors(['amount' => $e->getMessage()]);
            }

            return redirect()->route('approvals.index')->with('success', $result->status === 'executed'
                ? 'Transfert effectué avec succès.'
                : 'Virement soumis à validation : il sera effectué après les validations requises.');
        }

        try {
            $from = FundAccount::findOrFail($request->from_fund_account_id);
            $to = FundAccount::findOrFail($request->to_fund_account_id);

            $this->fundService->transfer(
                $from,
                $to,
                $request->input('amount'),
                $request->description,
                $request->user(),
                $request->input('idempotency_key'),
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('funds.index')
            ->with('success', 'Transfert effectué avec succès.');
    }

    public function cancelMovement(CancelFundMovementRequest $request, FundMovement $fundMovement): RedirectResponse
    {
        try {
            $this->fundService->cancelMovement($fundMovement, $request->cancellation_reason, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['cancellation_reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Mouvement annulé.');
    }

    public function recalculate(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage fund accounts'), 403);

        $this->fundService->recalculateAllBalances();

        return back()->with('success', 'Soldes recalculés avec succès.');
    }

    private function formatAccount(FundAccount $account, float $totalBalance, bool $detailed = false): array
    {
        $balance = (float) $account->current_balance;
        $percent = $totalBalance > 0 ? round(($balance / $totalBalance) * 100, 1) : 0;

        $data = [
            'id' => $account->id,
            'name' => $account->name,
            'slug' => $account->slug,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'type_color' => $account->type->color(),
            'type_icon' => $account->type->icon(),
            'balance' => $balance,
            'balance_formatted' => FundFormatter::money($balance, $account->currency),
            'currency' => $account->currency,
            'percent' => $percent,
            'is_active' => $account->is_active,
        ];

        if ($detailed) {
            $data['description'] = $account->description;
            $data['color'] = $account->color;
            $data['projects_count'] = $account->projects()->count();
            $data['is_system'] = in_array($account->slug, ['long-term-investment', 'family-projects', 'solidarity', 'education', 'treasury'], true);
        }

        return $data;
    }

    private function formatMovement(FundMovement $movement): array
    {
        $movement->loadMissing(['account', 'creator', 'relatedAccount']);

        return [
            'id' => $movement->id,
            'account' => $movement->account?->name ?? '—',
            'account_id' => $movement->fund_account_id,
            'type' => $movement->type->value,
            'type_label' => $movement->type->label(),
            'type_color' => $movement->type->color(),
            'direction' => $movement->direction,
            'direction_label' => $movement->direction === 'credit' ? 'Crédit' : 'Débit',
            'amount' => (float) $movement->amount,
            'amount_formatted' => FundFormatter::money($movement->amount, $movement->currency),
            'label' => $movement->label,
            'reference' => $movement->reference ?? '—',
            'movement_date' => $movement->movement_date?->format('d/m/Y'),
            'created_by' => $movement->creator?->name ?? '—',
            'status' => $movement->status->value,
            'status_label' => $movement->status->label(),
            'status_color' => $movement->status->color(),
            'related_account' => $movement->relatedAccount?->name,
        ];
    }

    private function allocationChartData($accounts, float $totalBalance): array
    {
        return $accounts->map(fn (FundAccount $a) => [
            'name' => $a->name,
            'value' => (float) $a->current_balance,
            'percent' => $totalBalance > 0 ? round(((float) $a->current_balance / $totalBalance) * 100, 1) : 0,
            'color' => $a->type->color(),
        ])->values()->all();
    }

    private function evolutionChartData(): array
    {
        $movements = FundMovement::validated()
            ->whereNotNull('movement_date')
            ->orderBy('movement_date')
            ->get(['movement_date', 'amount', 'direction']);

        $grouped = $movements->groupBy(fn (FundMovement $m) => $m->movement_date->format('Y-m'));

        $labels = [];
        $values = [];
        $cumulative = 0;

        foreach ($grouped as $key => $items) {
            [$year, $month] = explode('-', $key);
            $labels[] = sprintf('%02d/%s', (int) $month, $year);
            $net = $items->sum(fn (FundMovement $m) => $m->direction === 'credit' ? (float) $m->amount : -(float) $m->amount);
            $cumulative += $net;
            $values[] = round($cumulative, 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function permissions($user): array
    {
        return [
            'createMovement' => $user->can('create fund movements'),
            'transfer' => $user->can('transfer funds'),
            'manageRules' => $user->can('manage allocation rules'),
            'recalculate' => $user->can('manage fund accounts'),
            'cancel' => $user->can('create fund movements'),
            'viewMovements' => $user->can('view fund movements'),
            'manageAccounts' => $user->can('manage fund accounts'),
            'manageExamples' => $user->hasRole('super_admin') || $user->can('manage fund accounts'),
            'resetFunds' => $user->hasRole('super_admin'),
            'adjustBalance' => $user->can('manage fund accounts'),
        ];
    }

    public const RESET_PHRASE = 'REMISE A ZERO';

    /**
     * Mise en service : remet tous les comptes du fonds à zéro.
     * Les données d'exemple sont supprimées ; les autres mouvements sont archivés (conservés en base, plus comptés).
     */
    public function resetFunds(Request $request, \App\Services\ExampleDataService $examples): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $request->validate(['confirmation' => ['required', 'in:'.self::RESET_PHRASE]], ['confirmation.in' => __('Tapez exactement « :phrase » pour confirmer.', ['phrase' => self::RESET_PHRASE])]);

        $removed = $examples->remove($request->user());
        $archived = \Illuminate\Support\Facades\DB::transaction(function () {
            $count = FundMovement::query()->count();
            FundMovement::query()->delete();
            FundAccount::query()->each(fn (FundAccount $a) => $this->fundService->recalculateAccountBalance($a));

            return $count;
        });
        activity('funds')->causedBy($request->user())->event('fund.reset')
            ->withProperties(['archived_movements' => $archived, 'examples' => $removed])
            ->log('Remise à zéro des comptes du fonds (mise en service)');

        return back()->with('success', __('Comptes remis à zéro : :count mouvement(s) archivé(s), données d’exemple supprimées.', ['count' => $archived]));
    }

    /** Correction manuelle d'un solde : crée un ajustement (tracé et motivé) pour atteindre le solde indiqué. */
    public function adjustAccount(Request $request, FundAccount $fundAccount): RedirectResponse
    {
        abort_unless($request->user()->can('manage fund accounts'), 403);
        $data = $request->validate([
            'target_balance' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], ['reason.required' => 'Indiquez la raison de la correction.', 'reason.min' => 'Indiquez la raison de la correction (5 caractères minimum).']);

        $current = (float) $this->fundService->recalculateAccountBalance($fundAccount)->decimal();
        $difference = round((float) $data['target_balance'] - $current, 2);
        if (abs($difference) < 0.005) {
            return back()->with('error', 'Le solde est déjà de ce montant.');
        }
        $this->fundService->createMovement([
            'fund_account_id' => $fundAccount->id, 'type' => FundMovementType::Adjustment,
            'amount' => number_format(abs($difference), 2, '.', ''), 'currency' => $fundAccount->currency,
            'direction' => $difference > 0 ? 'credit' : 'debit', 'label' => 'Correction de solde — '.$fundAccount->name,
            'description' => $data['reason'], 'reference' => 'AJUST-'.now()->format('YmdHis'),
            'created_by' => $request->user()->id, 'validated_by' => $request->user()->id,
        ]);

        return back()->with('success', __('Solde corrigé : :before → :after.', [
            'before' => FundFormatter::money($current, $fundAccount->currency), 'after' => FundFormatter::money($data['target_balance'], $fundAccount->currency),
        ]));
    }

    /** Nouveau compte de fonds (au-delà des 5 comptes d'origine). */
    public function storeAccount(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage fund accounts'), 403);
        $data = $this->validateAccount($request);
        $base = \Illuminate\Support\Str::slug($data['name']) ?: 'compte';
        $slug = $base;
        for ($i = 2; FundAccount::where('slug', $slug)->exists(); $i++) $slug = "{$base}-{$i}";
        $account = FundAccount::create([
            ...$data, 'slug' => $slug, 'type' => \App\Enums\FundAccountType::Other, 'currency' => \App\Support\FundCurrency::code(),
            'current_balance' => 0, 'is_active' => true, 'sort_order' => (int) FundAccount::max('sort_order') + 1, 'created_by' => $request->user()->id,
        ]);
        activity('funds')->performedOn($account)->causedBy($request->user())->event('fund.account.created')->withProperties($data)->log('Compte de fonds créé : '.$account->name);

        return back()->with('success', 'Compte de fonds créé. Pensez à l’ajouter aux règles d’allocation si les cotisations doivent l’alimenter.');
    }

    public function updateAccount(Request $request, FundAccount $fundAccount): RedirectResponse
    {
        abort_unless($request->user()->can('manage fund accounts'), 403);
        $data = $this->validateAccount($request, $fundAccount);
        $before = $fundAccount->only(array_keys($data));
        $fundAccount->update($data);
        activity('funds')->performedOn($fundAccount)->causedBy($request->user())->event('fund.account.updated')
            ->withProperties(['before' => $before, 'after' => $data])->log('Compte de fonds modifié : '.$fundAccount->name);

        return back()->with('success', 'Compte de fonds mis à jour.');
    }

    public function toggleAccount(Request $request, FundAccount $fundAccount): RedirectResponse
    {
        abort_unless($request->user()->can('manage fund accounts'), 403);
        if ($fundAccount->is_active) {
            if ((float) $fundAccount->current_balance != 0.0) {
                return back()->with('error', 'Le compte doit avoir un solde nul pour être désactivé : transférez d’abord son argent.');
            }
            if ($fundAccount->projects()->whereIn('status', ['approved_by_family', 'funded', 'in_progress'])->exists()) {
                return back()->with('error', 'Des projets en cours dépendent de ce compte.');
            }
            if ($fundAccount->slug === 'treasury') {
                return back()->with('error', 'La trésorerie ne peut pas être désactivée.');
            }
        }
        $fundAccount->update(['is_active' => ! $fundAccount->is_active]);
        activity('funds')->performedOn($fundAccount)->causedBy($request->user())->event('fund.account.toggled')
            ->log(($fundAccount->is_active ? 'Compte de fonds réactivé : ' : 'Compte de fonds désactivé : ').$fundAccount->name);

        return back()->with('success', $fundAccount->is_active ? 'Compte réactivé.' : 'Compte désactivé.');
    }

    private function validateAccount(Request $request, ?FundAccount $account = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', \Illuminate\Validation\Rule::unique('fund_accounts', 'name')->ignore($account?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'in:gold,blue,green,red,purple,slate,orange,teal'],
        ], ['name.unique' => 'Un compte porte déjà ce nom.']);
    }

    /** Données d'exemple (projets et mouvements fictifs marqués « Exemple — »). */
    public function storeExamples(Request $request, \App\Services\ExampleDataService $examples): RedirectResponse
    {
        abort_unless($this->permissions($request->user())['manageExamples'], 403);
        try {
            $count = $examples->create($request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':count projets d’exemple créés.', ['count' => $count]));
    }

    public function destroyExamples(Request $request, \App\Services\ExampleDataService $examples): RedirectResponse
    {
        abort_unless($this->permissions($request->user())['manageExamples'], 403);
        $result = $examples->remove($request->user());

        return back()->with('success', __('Données d’exemple supprimées : :projects projet(s), :movements mouvement(s). Les soldes ont été recalculés.', $result));
    }
}
