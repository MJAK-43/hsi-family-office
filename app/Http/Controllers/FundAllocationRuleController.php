<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFundAllocationRuleRequest;
use App\Http\Requests\UpdateFundAllocationRuleRequest;
use App\Models\FundAccount;
use App\Models\FundAllocationRule;
use App\Models\FundAllocationRuleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FundAllocationRuleController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->can('manage allocation rules'), 403);

        $rules = FundAllocationRule::with(['items.account'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (FundAllocationRule $rule) => $this->formatRule($rule));

        return Inertia::render('FundAllocationRules/Index', [
            'rules' => $rules,
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('manage allocation rules'), 403);

        return Inertia::render('FundAllocationRules/Create', $this->formOptions());
    }

    public function store(StoreFundAllocationRuleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            if ($request->boolean('is_default')) {
                FundAllocationRule::query()->update(['is_default' => false]);
            }

            $rule = FundAllocationRule::create([
                'name' => $request->name,
                'description' => $request->description,
                'is_default' => $request->boolean('is_default'),
                'is_active' => $request->boolean('is_active', true),
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->items as $item) {
                FundAllocationRuleItem::create([
                    'fund_allocation_rule_id' => $rule->id,
                    'fund_account_id' => $item['fund_account_id'],
                    'percentage' => $item['percentage'],
                ]);
            }

            activity('funds')
                ->performedOn($rule)
                ->causedBy($request->user())
                ->event('fund.allocation.rule.created')
                ->log('Règle d\'allocation créée');
        });

        return redirect()->route('fund-allocation-rules.index')
            ->with('success', 'Règle d\'allocation créée.');
    }

    public function edit(FundAllocationRule $fundAllocationRule): Response
    {
        abort_unless(auth()->user()->can('manage allocation rules'), 403);

        $fundAllocationRule->load('items');

        return Inertia::render('FundAllocationRules/Edit', [
            'rule' => $this->formatRule($fundAllocationRule, detailed: true),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateFundAllocationRuleRequest $request, FundAllocationRule $fundAllocationRule): RedirectResponse
    {
        DB::transaction(function () use ($request, $fundAllocationRule) {
            if ($request->boolean('is_default')) {
                FundAllocationRule::where('id', '!=', $fundAllocationRule->id)->update(['is_default' => false]);
            }

            $fundAllocationRule->update([
                'name' => $request->name,
                'description' => $request->description,
                'is_default' => $request->boolean('is_default'),
                'is_active' => $request->boolean('is_active'),
            ]);

            $fundAllocationRule->items()->delete();

            foreach ($request->items as $item) {
                FundAllocationRuleItem::create([
                    'fund_allocation_rule_id' => $fundAllocationRule->id,
                    'fund_account_id' => $item['fund_account_id'],
                    'percentage' => $item['percentage'],
                ]);
            }

            activity('funds')
                ->performedOn($fundAllocationRule)
                ->causedBy($request->user())
                ->event('fund.allocation.rule.updated')
                ->log('Règle d\'allocation modifiée');
        });

        return redirect()->route('fund-allocation-rules.index')
            ->with('success', 'Règle d\'allocation mise à jour.');
    }

    public function activate(FundAllocationRule $fundAllocationRule): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage allocation rules'), 403);

        $fundAllocationRule->update(['is_active' => true]);

        return back()->with('success', 'Règle activée.');
    }

    public function deactivate(FundAllocationRule $fundAllocationRule): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage allocation rules'), 403);

        $fundAllocationRule->update([
            'is_active' => false,
            'is_default' => false,
        ]);

        return back()->with('success', 'Règle désactivée.');
    }

    public function setDefault(FundAllocationRule $fundAllocationRule): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage allocation rules'), 403);

        FundAllocationRule::query()->update(['is_default' => false]);
        $fundAllocationRule->update(['is_default' => true, 'is_active' => true]);

        activity('funds')
            ->performedOn($fundAllocationRule)
            ->causedBy(auth()->user())
            ->event('fund.allocation.rule.default_changed')
            ->log('Règle d\'allocation définie par défaut');

        return back()->with('success', 'Règle définie par défaut.');
    }

    private function formOptions(): array
    {
        return [
            'accounts' => FundAccount::where('is_active', true)
                ->where('slug', '!=', 'treasury')
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ];
    }

    private function formatRule(FundAllocationRule $rule, bool $detailed = false): array
    {
        $rule->loadMissing(['items.account']);

        $data = [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'is_default' => $rule->is_default,
            'is_active' => $rule->is_active,
            'items' => $rule->items->map(fn (FundAllocationRuleItem $item) => [
                'fund_account_id' => $item->fund_account_id,
                'account_name' => $item->account?->name,
                'percentage' => (float) $item->percentage,
            ]),
            'total_percentage' => round($rule->items->sum('percentage'), 2),
        ];

        if ($detailed) {
            $data['items_form'] = $rule->items->map(fn (FundAllocationRuleItem $item) => [
                'fund_account_id' => $item->fund_account_id,
                'percentage' => (float) $item->percentage,
            ])->values();
        }

        return $data;
    }
}
