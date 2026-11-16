<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContributionPlanRequest;
use App\Http\Requests\UpdateContributionPlanRequest;
use App\Models\ContributionPlan;
use App\Models\Country;
use App\Enums\ContributionFrequency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContributionPlanController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->can('manage contribution plans'), 403);

        $plans = ContributionPlan::with(['country', 'creator'])
            ->orderBy('name')
            ->get()
            ->map(fn (ContributionPlan $plan) => $this->formatPlan($plan));

        return Inertia::render('ContributionPlans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('manage contribution plans'), 403);

        return Inertia::render('ContributionPlans/Create', $this->formOptions());
    }

    public function store(StoreContributionPlanRequest $request): RedirectResponse
    {
        if ($request->boolean('is_default')) {
            ContributionPlan::query()->update(['is_default' => false]);
        }

        $plan = ContributionPlan::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        activity('contributions')
            ->performedOn($plan)
            ->causedBy($request->user())
            ->event('contribution.plan.created')
            ->log('Plan de cotisation créé');

        return redirect()->route('contribution-plans.index')
            ->with('success', 'Plan de cotisation créé.');
    }

    public function edit(ContributionPlan $contributionPlan): Response
    {
        abort_unless(auth()->user()->can('manage contribution plans'), 403);

        return Inertia::render('ContributionPlans/Edit', [
            'plan' => $this->formatPlan($contributionPlan, detailed: true),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateContributionPlanRequest $request, ContributionPlan $contributionPlan): RedirectResponse
    {
        if ($contributionPlan->is_regulatory) {
            return back()->with('error', 'La contribution trimestrielle du règlement se règle dans « Paramètres du fonds ».');
        }
        if ($request->boolean('is_default')) {
            ContributionPlan::where('id', '!=', $contributionPlan->id)->update(['is_default' => false]);
        }

        $contributionPlan->update($request->validated());

        activity('contributions')
            ->performedOn($contributionPlan)
            ->causedBy($request->user())
            ->event('contribution.plan.updated')
            ->log('Plan de cotisation modifié');

        return redirect()->route('contribution-plans.index')
            ->with('success', 'Plan de cotisation mis à jour.');
    }

    public function activate(ContributionPlan $contributionPlan): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage contribution plans'), 403);

        $contributionPlan->update(['is_active' => true]);

        return back()->with('success', 'Plan activé.');
    }

    public function deactivate(ContributionPlan $contributionPlan): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage contribution plans'), 403);

        if ($contributionPlan->is_regulatory) {
            return back()->with('error', 'La contribution trimestrielle du règlement se règle dans « Paramètres du fonds ».');
        }
        $contributionPlan->update(['is_active' => false]);

        return back()->with('success', 'Plan désactivé.');
    }

    public function destroy(ContributionPlan $contributionPlan): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage contribution plans'), 403);

        if ($contributionPlan->is_regulatory) {
            return back()->with('error', 'La contribution trimestrielle du règlement se règle dans « Paramètres du fonds ».');
        }
        $contributionPlan->delete();

        activity('contributions')
            ->performedOn($contributionPlan)
            ->causedBy(auth()->user())
            ->event('contribution.plan.deleted')
            ->log('Plan de cotisation supprimé');

        return redirect()->route('contribution-plans.index')
            ->with('success', 'Plan de cotisation supprimé.');
    }

    private function formOptions(): array
    {
        return [
            'countries' => Country::forDisplay(Country::where('is_active', true)->orderBy('name')->get(['id', 'name', 'flag_emoji'])),
            'frequencies' => ContributionFrequency::options(),
            'currencies' => [\App\Support\FundCurrency::code()],
        ];
    }

    private function formatPlan(ContributionPlan $plan, bool $detailed = false): array
    {
        $plan->loadMissing(['country', 'creator']);

        $data = [
            'id' => $plan->id,
            'is_regulatory' => (bool) $plan->is_regulatory,
            'name' => $plan->name,
            'amount' => (float) $plan->amount,
            'currency' => $plan->currency,
            'frequency' => $plan->frequency->value,
            'frequency_label' => $plan->frequency->label(),
            'country' => $plan->country ? [
                'id' => $plan->country->id,
                'name' => $plan->country->name,
                'flag_emoji' => $plan->country->flag_emoji,
            ] : null,
            'is_default' => $plan->is_default,
            'is_active' => $plan->is_active,
            'auto_generate' => $plan->auto_generate,
            'starts_on' => $plan->starts_on?->format('Y-m-d'),
            'due_day' => $plan->due_day,
            'grace_days' => $plan->grace_days,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'description' => $plan->description,
                'country_id' => $plan->country_id,
                'last_generated_at' => $plan->last_generated_at?->format('d/m/Y H:i'),
            ]);
        }

        return $data;
    }
}
