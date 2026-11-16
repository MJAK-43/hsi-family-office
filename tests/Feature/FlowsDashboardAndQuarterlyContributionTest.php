<?php

namespace Tests\Feature;

use App\Enums\ContributionDueStatus;
use App\Enums\ContributionPaymentStatus;
use App\Enums\FundMovementType;
use App\Models\ApplicationSetting;
use App\Models\ContributionDue;
use App\Models\ContributionPayment;
use App\Models\ContributionPlan;
use App\Models\FundAccount;
use App\Models\MemberProfile;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Zone;
use App\Services\FundFlowService;
use App\Services\FundService;
use App\Support\ContributionRules;
use App\Support\FundRules;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FundAccountSeeder;
use Database\Seeders\FundAllocationRuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FlowsDashboardAndQuarterlyContributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FundRules::flush();
        $this->seed([RolePermissionSeeder::class, CountrySeeder::class, FundAccountSeeder::class, FundAllocationRuleSeeder::class]);
        TransactionType::query()->update(['required_approvals' => 1]);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        FundRules::flush();
        CarbonImmutable::setTestNow();
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string ...$roles): User
    {
        $user = User::factory()->create();
        $user->syncRoles($roles ?: ['member']);
        MemberProfile::create(['user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    // ------------------------------------------------------------ Flux financiers

    public function test_flow_diagram_links_income_categories_accounts_and_expenses(): void
    {
        $funds = app(FundService::class);
        $projects = FundAccount::where('slug', 'family-projects')->firstOrFail();
        $treasury = FundAccount::where('slug', 'treasury')->firstOrFail();
        $funds->createMovement(['fund_account_id' => $treasury->id, 'type' => FundMovementType::ManualIncome, 'amount' => '1000.00', 'currency' => 'USD', 'direction' => 'credit', 'label' => 'Don']);
        $funds->createMovement(['fund_account_id' => $treasury->id, 'type' => FundMovementType::PenaltyIncome, 'amount' => '20.00', 'currency' => 'USD', 'direction' => 'credit', 'label' => 'Pénalité']);
        $funds->transfer($treasury, $projects, '600.00', 'Vers projets', User::factory()->create());
        $funds->createMovement(['fund_account_id' => $projects->id, 'type' => FundMovementType::ManualExpense, 'amount' => '150.00', 'currency' => 'USD', 'direction' => 'debit', 'label' => 'Frais']);

        $flow = app(FundFlowService::class)->sankey(['from' => CarbonImmutable::today()->subYear(), 'to' => CarbonImmutable::today()->endOfDay()]);

        $this->assertEqualsWithDelta(1020, $flow['totals']['in'], 0.01);
        $this->assertEqualsWithDelta(150, $flow['totals']['out'], 0.01);
        $links = collect($flow['links'])->keyBy(fn ($l) => $l['source'].'>'.$l['target']);
        $this->assertEqualsWithDelta(1000, $links['in:manual_in>acc:'.$treasury->id]['value'], 0.01);
        $this->assertEqualsWithDelta(600, $links['acc:'.$treasury->id.'>acc:'.$projects->id]['value'], 0.01);
        $this->assertEqualsWithDelta(150, $links['acc:'.$projects->id.'>out:manual_out']['value'], 0.01);
        // Ce qui n'est pas dépensé reste dans les comptes.
        $this->assertEqualsWithDelta(420, $links['acc:'.$treasury->id.'>balance:kept']['value'], 0.01);
        $this->assertEqualsWithDelta(450, $links['acc:'.$projects->id.'>balance:kept']['value'], 0.01);

        // Vue d'un seul compte : les virements deviennent des catégories.
        $single = app(FundFlowService::class)->sankey(['from' => CarbonImmutable::today()->subYear(), 'to' => CarbonImmutable::today()->endOfDay(), 'fund_account_id' => $projects->id]);
        $this->assertContains('Virement depuis '.$treasury->name, collect($single['nodes'])->pluck('label'));
    }

    public function test_fund_pages_reports_and_dashboard_receive_the_flows(): void
    {
        $treasurer = $this->user('treasurer');
        $account = FundAccount::where('slug', 'treasury')->firstOrFail();
        $this->actingAs($treasurer)->get(route('funds.index', ['flow_period' => 'year']))
            ->assertInertia(fn (Assert $page) => $page->has('flow.nodes')->where('flowPeriod', 'year'));
        $this->actingAs($treasurer)->get(route('funds.show', $account))
            ->assertInertia(fn (Assert $page) => $page->has('flow.links')->where('flow.account', $account->name));
        $this->actingAs($treasurer)->get(route('reports.index'))
            ->assertInertia(fn (Assert $page) => $page->has('report.flows.totals'));
        $this->actingAs($treasurer)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('flow.totals')->where('hiddenWidgets', []));

        // Un membre sans accès au fonds ne reçoit pas les flux sur son tableau de bord.
        $this->actingAs($this->user('guest'))->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('visibility.funds', false)->where('flow', null));
    }

    // ------------------------------------------------------------ Tableau de bord

    public function test_each_person_chooses_the_dashboard_cards_to_display(): void
    {
        $member = $this->user('member');
        $this->actingAs($member)->patch(route('preferences.dashboard'), ['hidden' => ['votes', 'stat_votes']])->assertRedirect();
        $this->assertSame(['votes', 'stat_votes'], $member->fresh()->preferences['dashboard_hidden']);
        $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('hiddenWidgets', ['votes', 'stat_votes']));

        $this->actingAs($member)->patch(route('preferences.dashboard'), ['hidden' => ['not_a_card']])->assertSessionHasErrors('hidden.0');
        $this->actingAs($member)->patch(route('preferences.dashboard'), ['hidden' => []]);
        $this->assertSame([], $member->fresh()->preferences['dashboard_hidden']);
    }

    // ------------------------------------------------------------ Cotisations (section 4)

    public function test_the_regulatory_quarterly_plan_follows_the_fund_rules(): void
    {
        $plan = ContributionRules::plan();
        $this->assertTrue($plan->is_regulatory);
        $this->assertSame('quarterly', $plan->frequency->value);
        $this->assertSame('75.00', (string) $plan->amount);
        $this->assertSame(15, (int) $plan->due_day);
        $this->assertSame(1, ContributionPlan::where('is_default', true)->count());

        $admin = $this->user('super_admin');
        $rules = collect(FundRules::all())->mapWithKeys(fn ($r) => [$r['key'] => $r['value']])->all();
        $rules['contribution_quarterly_amount'] = 90;
        $rules['contribution_due_day'] = 10;
        $this->actingAs($admin)->put(route('fund-settings.rules.update'), ['rules' => $rules])->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertSame('90.00', (string) $plan->amount);
        $this->assertSame(10, (int) $plan->due_day);

        // Le plan du règlement ne se modifie ni ne se supprime depuis l'écran des plans.
        $this->actingAs($admin)->delete(route('contribution-plans.destroy', $plan))->assertSessionHas('error');
        $this->assertNotNull($plan->fresh());
    }

    public function test_a_late_quarterly_contribution_gets_its_penalty_and_fills_the_register(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2027-01-28 10:00:00');
        CarbonImmutable::setTestNow('2027-01-28 10:00:00');
        $zone = Zone::firstOrCreate(['code' => 'europe'], ['name' => 'Europe', 'is_active' => true]);
        $treasurer = $this->user('treasurer');
        $zone->treasurers()->sync([$treasurer->id]);
        $member = $this->user('member');
        $member->memberProfile->update(['zone_id' => $zone->id]);

        $this->actingAs($member)->post(route('contributions.store'), [
            'kind' => 'quarterly', 'quarter' => 1, 'payment_year' => 2027, 'amount' => 79, 'currency' => 'USD',
            'payment_method' => 'bank_transfer', 'paid_at' => '2027-01-20',
        ])->assertSessionHasNoErrors();
        $payment = ContributionPayment::where('user_id', $member->id)->firstOrFail();
        $this->assertSame('T1 2027', $payment->payment_period_label);
        $this->assertSame(1, (int) $payment->payment_month);
        $this->assertSame(ContributionRules::plan()->id, $payment->contribution_plan_id);
        // Le trésorier de la zone est prévenu.
        Notification::assertSentTo($treasurer, \App\Notifications\FundNotification::class);

        $this->actingAs($treasurer)->get(route('contributions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payments.data.0.confirmation_deadline', '01/02/2027')
                ->where('payments.data.0.quarter', 1)
                ->where('register.year', 2027)
                ->where('register.rows', fn ($rows) => collect($rows)->firstWhere('id', $member->id)['quarters'][0]['state'] === 'submitted'));

        $this->actingAs($treasurer)->patch(route('contributions.validate', $payment))->assertSessionHasNoErrors();
        $due = ContributionDue::where('user_id', $member->id)->where('period_key', '2027-Q1')->firstOrFail();
        $this->assertSame(ContributionDueStatus::Paid, $due->status);
        // Reçu le 20 janvier : 1 période de 7 jours entamée depuis le 16 → 2 $.
        $this->assertSame('2.00', (string) $due->penalty_amount);
        $this->assertSame($payment->id, $due->contribution_payment_id);
        $this->assertSame(ContributionPaymentStatus::Validated, $payment->fresh()->status);
        // Les 2 $ de pénalité sont inscrits à part (trésorerie) ; le reste (77 $) est alloué comme cotisation.
        $penalty = \App\Models\FundMovement::where('contribution_payment_id', $payment->id)->where('type', 'penalty_income')->first();
        $this->assertNotNull($penalty);
        $this->assertSame('2.00', (string) $penalty->amount);
        $this->assertSame('treasury', $penalty->account->slug);
        $this->assertEqualsWithDelta(77, (float) \App\Models\FundMovement::where('contribution_payment_id', $payment->id)->where('type', 'contribution_allocation')->sum('amount'), 0.001);

        $this->actingAs($member)->get(route('contributions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('register.own_only', true)
                ->has('register.rows', 1)
                ->where('register.rows.0.quarters.0.state', 'paid_late')
                ->where('register.rows.0.quarters.1.state', 'upcoming'));
    }

    public function test_quarterly_declaration_requires_a_quarter_and_a_payment_date(): void
    {
        $member = $this->user('member');
        $this->actingAs($member)->post(route('contributions.store'), [
            'kind' => 'quarterly', 'amount' => 75, 'currency' => 'USD', 'payment_method' => 'bank_transfer',
        ])->assertSessionHasErrors(['quarter', 'payment_year', 'paid_at']);

        $this->actingAs($member)->post(route('contributions.store'), [
            'kind' => 'voluntary', 'amount' => 30, 'currency' => 'USD', 'payment_method' => 'bank_transfer',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Contribution volontaire', ContributionPayment::latest('id')->value('payment_period_label'));
        $this->assertNull(ContributionPayment::latest('id')->value('contribution_plan_id'));
    }
}
