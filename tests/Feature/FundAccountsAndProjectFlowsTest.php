<?php

namespace Tests\Feature;

use App\Enums\FundMovementType;
use App\Enums\ProjectStatus;
use App\Models\FundAccount;
use App\Models\FundMovement;
use App\Models\MemberProfile;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\ExampleDataService;
use App\Services\FundFlowService;
use App\Services\FundService;
use App\Support\FundRules;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FundAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FundAccountsAndProjectFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FundRules::flush();
        $this->seed([RolePermissionSeeder::class, CountrySeeder::class, FundAccountSeeder::class, \Database\Seeders\FundAllocationRuleSeeder::class]);
        TransactionType::query()->update(['required_approvals' => 1]);
        Notification::fake();
    }

    private function user(string ...$roles): User
    {
        $user = User::factory()->create();
        $user->syncRoles($roles ?: ['member']);
        MemberProfile::create(['user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    private function account(string $slug): FundAccount
    {
        return FundAccount::where('slug', $slug)->firstOrFail();
    }

    private function credit(FundAccount $account, float $amount): void
    {
        app(FundService::class)->createMovement(['fund_account_id' => $account->id, 'type' => FundMovementType::ManualIncome, 'amount' => (string) $amount, 'currency' => 'USD', 'direction' => 'credit', 'label' => 'Apport']);
    }

    public function test_treasurers_can_add_edit_and_deactivate_fund_accounts(): void
    {
        $treasurer = $this->user('treasurer');
        $this->actingAs($treasurer)->post(route('funds.accounts.store'), ['name' => 'Fonds d’urgence', 'description' => 'Imprévus', 'color' => 'red'])->assertSessionHas('success');
        $account = FundAccount::where('name', 'Fonds d’urgence')->firstOrFail();
        $this->assertSame('other', $account->type->value);
        $this->assertSame('0.00', (string) $account->current_balance);
        $this->assertSame(6, FundAccount::where('is_active', true)->count());

        $this->actingAs($treasurer)->post(route('funds.accounts.store'), ['name' => 'Fonds d’urgence'])->assertSessionHasErrors('name');
        $this->actingAs($treasurer)->put(route('funds.accounts.update', $account), ['name' => 'Urgences', 'color' => 'teal'])->assertSessionHas('success');
        $this->assertSame('Urgences', $account->fresh()->name);

        // Un compte avec de l'argent ne se désactive pas.
        $this->credit($account, 50);
        $this->actingAs($treasurer)->patch(route('funds.accounts.toggle', $account))->assertSessionHas('error');
        $this->assertTrue($account->fresh()->is_active);

        $empty = FundAccount::where('slug', 'education')->first();
        $this->actingAs($treasurer)->patch(route('funds.accounts.toggle', $empty))->assertSessionHas('success');
        $this->assertFalse($empty->fresh()->is_active);

        $this->actingAs($this->user('member'))->post(route('funds.accounts.store'), ['name' => 'Interdit'])->assertForbidden();
    }

    public function test_a_project_must_choose_its_fund_account_which_pays_it(): void
    {
        $member = $this->user('member');
        $type = ProjectType::where('code', 'investment')->firstOrFail();
        $payload = ['project_type_id' => $type->id, 'title' => 'Ferme avicole', 'description' => 'Test', 'sector' => 'agriculture', 'total_budget' => 1000, 'personal_contribution' => 500, 'requested_amount' => 500, 'currency' => 'USD'];

        $this->actingAs($member)->post(route('projects.store'), $payload)->assertSessionHasErrors('fund_account_id');
        $this->actingAs($member)->post(route('projects.store'), [...$payload, 'fund_account_id' => $this->account('treasury')->id])->assertSessionHasErrors('fund_account_id');

        $solidarity = $this->account('solidarity');
        $this->actingAs($member)->post(route('projects.store'), [...$payload, 'fund_account_id' => $solidarity->id])->assertSessionHasNoErrors();
        $project = Project::where('title', 'Ferme avicole')->firstOrFail();
        $this->assertSame($solidarity->id, $project->fund_account_id);

        // Le décaissement part du compte choisi.
        $this->credit($solidarity, 800);
        $movement = app(FundService::class)->createProjectFundingMovement($project, $this->user('treasurer'));
        $this->assertSame($solidarity->id, $movement->fund_account_id);
        $this->assertSame('300.00', (string) $solidarity->fresh()->current_balance);

        $this->actingAs($member)->get(route('projects.create'))->assertInertia(fn (Assert $page) => $page->has('fundAccounts', 4));
    }

    public function test_account_flows_detail_each_project_funded_or_awaiting_funding(): void
    {
        $owner = $this->user('member');
        $account = $this->account('family-projects');
        $this->credit($account, 5000);
        $type = ProjectType::where('code', 'investment')->firstOrFail();
        $make = fn (string $title, ProjectStatus $status, float $amount) => Project::create([
            'owner_id' => $owner->id, 'project_type_id' => $type->id, 'fund_account_id' => $account->id, 'title' => $title, 'description' => 'x',
            'sector' => 'commerce', 'total_budget' => $amount * 2, 'personal_contribution' => $amount, 'requested_amount' => $amount, 'currency' => 'USD', 'status' => $status, 'created_by' => $owner->id,
        ]);
        $funded = $make('Épicerie', ProjectStatus::ApprovedByFamily, 1200);
        app(FundService::class)->createProjectFundingMovement($funded, $owner);
        $funded->update(['status' => ProjectStatus::InProgress]);
        $done = $make('Taxi', ProjectStatus::ApprovedByFamily, 800);
        app(FundService::class)->createProjectFundingMovement($done, $owner);
        $done->update(['status' => ProjectStatus::Completed]);
        $waiting = $make('Salon de coiffure', ProjectStatus::ApprovedByFamily, 700);
        $make('Brouillon ignoré', ProjectStatus::Draft, 300);

        $flow = app(FundFlowService::class)->sankey(['from' => CarbonImmutable::today()->subYear(), 'to' => CarbonImmutable::today()->endOfDay(), 'fund_account_id' => $account->id, 'detail_projects' => true]);
        $nodes = collect($flow['nodes'])->keyBy('id');
        $this->assertSame('in_progress', $nodes['proj:'.$funded->id]['stage']);
        $this->assertSame('completed', $nodes['proj:'.$done->id]['stage']);
        $this->assertSame('pending', $nodes['pending:'.$waiting->id]['stage']);
        $this->assertEqualsWithDelta(2000, $flow['totals']['out'], 0.01);
        $this->assertEqualsWithDelta(700, $flow['totals']['planned'], 0.01);
        $this->assertCount(3, $flow['projects']);
        $this->assertSame(['in_progress', 'completed', 'pending'], collect($flow['projects'])->sortBy('id')->pluck('stage')->values()->all());

        $this->actingAs($this->user('treasurer'))->get(route('funds.index'))
            ->assertInertia(fn (Assert $page) => $page->has('accountFlows', 5)->where('accountFlows', fn ($flows) => collect($flows)->firstWhere('account.id', $account->id)['flow']['totals']['planned'] == 700));
    }

    public function test_example_data_is_created_marked_and_removed_without_touching_real_balances(): void
    {
        $admin = $this->user('super_admin');
        $real = $this->account('family-projects');
        $this->credit($real, 40);

        $this->actingAs($admin)->post(route('funds.examples.store'))->assertSessionHas('success');
        // 2 projets par compte + 1 prêt d'exemple remboursé en partie (Projets familiaux).
        $this->assertSame(9, Project::where('is_example', true)->count());
        foreach (['family-projects', 'solidarity', 'education', 'long-term-investment'] as $slug) {
            $projects = Project::where('fund_account_id', $this->account($slug)->id)->where('is_example', true)->get();
            $this->assertCount($slug === 'family-projects' ? 3 : 2, $projects);
            $this->assertTrue($projects->every(fn ($p) => str_starts_with($p->title, ExampleDataService::PREFIX)));
            $this->assertSame(1, $projects->where('status', ProjectStatus::ApprovedByFamily)->count());
        }
        $this->assertTrue(FundMovement::where('is_example', true)->exists());
        $flow = app(FundFlowService::class)->sankey(['from' => CarbonImmutable::today()->subYears(2), 'to' => CarbonImmutable::today()->endOfDay()]);
        $labels = collect($flow['breakdown']['in'])->pluck('label');
        foreach (['Cotisations', 'Contributions volontaires', 'Pénalités de cotisation', 'Prêts remboursés (capital)', 'Intérêts des prêts', 'Pénalités de remboursement', 'Intérêts et revenus bancaires', 'Dons reçus (exemple)', 'Ajustements (+)', 'Apports fictifs (exemple)'] as $label) {
            $this->assertContains($label, $labels, $label);
        }
        $this->actingAs($admin)->post(route('funds.examples.store'))->assertSessionHas('error');

        $this->actingAs($admin)->delete(route('funds.examples.destroy'))->assertSessionHas('success');
        $this->assertSame(0, Project::withTrashed()->where('is_example', true)->count());
        $this->assertSame(0, FundMovement::withTrashed()->where('is_example', true)->count());
        $this->assertSame('40.00', (string) $real->fresh()->current_balance);
        $this->assertSame('0.00', (string) $this->account('solidarity')->fresh()->current_balance);
        $this->assertSame(0, \App\Models\ContributionPayment::withTrashed()->where('is_example', true)->count());
        $this->assertSame(0, TransactionType::where('code', 'like', 'example_%')->count());
        $this->assertSame(0, \App\Models\ApprovalRequest::where('title', 'like', 'Exemple — %')->count());

        $this->actingAs($this->user('member'))->post(route('funds.examples.store'))->assertForbidden();
    }

    /** nginx refuse les réponses dont les en-têtes dépassent 4 Ko (erreur 502) : l'en-tête « Link » des scripts préchargés est plafonné. */
    public function test_heavy_pages_keep_their_response_headers_small(): void
    {
        $treasurer = $this->user('super_admin');
        foreach ([route('dashboard'), route('funds.index'), route('reports.index'), route('documents.index')] as $url) {
            $response = $this->actingAs($treasurer)->get($url)->assertOk();
            $link = (string) $response->headers->get('Link');
            $this->assertLessThanOrEqual(8, $link === '' ? 0 : substr_count($link, 'rel='), $url);
            $this->assertLessThan(2500, strlen((string) $response->headers), $url);
        }
    }

    /** Un type d'entrée créé par l'administration apparaît sous son nom dans le diagramme de flux. */
    public function test_custom_income_types_appear_by_name_in_the_flow_diagram(): void
    {
        $treasurer = $this->user('treasurer');
        $this->actingAs($treasurer)->post(route('fund-settings.transaction-types.store'), [
            'name' => 'Dons reçus', 'direction' => 'in', 'required_approvals' => 1, 'approver_roles' => ['treasurer'], 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $type = TransactionType::where('name', 'Dons reçus')->firstOrFail();
        $account = $this->account('solidarity');

        $this->actingAs($treasurer)->post(route('approvals.store'), [
            'transaction_type_id' => $type->id, 'title' => 'Don de tonton Paul', 'amount' => 250, 'fund_account_id' => $account->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('250.00', (string) $account->fresh()->current_balance);

        $flow = app(FundFlowService::class)->sankey(['from' => CarbonImmutable::today()->subYear(), 'to' => CarbonImmutable::today()->endOfDay()]);
        $node = collect($flow['nodes'])->firstWhere('label', 'Dons reçus');
        $this->assertNotNull($node);
        $this->assertSame('in', $node['kind']);
        $this->assertSame('Dons reçus', $flow['breakdown']['in'][0]['label']);
        $this->assertEqualsWithDelta(250, $flow['breakdown']['in'][0]['total'], 0.01);
    }

    public function test_balances_can_be_corrected_and_everything_reset_to_zero_at_go_live(): void
    {
        $admin = $this->user('super_admin');
        $account = $this->account('education');
        $this->credit($account, 500);

        $this->actingAs($admin)->post(route('funds.accounts.adjust', $account), ['target_balance' => 450, 'reason' => 'Rapprochement bancaire'])->assertSessionHas('success');
        $this->assertSame('450.00', (string) $account->fresh()->current_balance);
        $adjustment = FundMovement::where('type', FundMovementType::Adjustment)->latest('id')->first();
        $this->assertSame('debit', $adjustment->direction);
        $this->assertSame('Rapprochement bancaire', $adjustment->description);
        $this->actingAs($admin)->post(route('funds.accounts.adjust', $account), ['target_balance' => 450, 'reason' => 'Rien à faire'])->assertSessionHas('error');

        $this->actingAs($admin)->post(route('funds.examples.store'));
        $this->actingAs($admin)->post(route('funds.reset'), ['confirmation' => 'oui'])->assertSessionHasErrors('confirmation');
        $this->actingAs($this->user('treasurer'))->post(route('funds.reset'), ['confirmation' => 'REMISE A ZERO'])->assertForbidden();
        $this->actingAs($admin)->post(route('funds.reset'), ['confirmation' => 'REMISE A ZERO'])->assertSessionHas('success');

        $this->assertSame(0, FundMovement::count());
        $this->assertGreaterThan(0, FundMovement::onlyTrashed()->count());
        $this->assertSame(0, Project::where('is_example', true)->count());
        $this->assertTrue(FundAccount::all()->every(fn ($a) => (float) $a->current_balance === 0.0));
        $this->assertDatabaseHas('activity_log', ['event' => 'fund.reset']);
    }

    public function test_bank_interest_can_be_entered_as_a_transaction(): void
    {
        $treasurer = $this->user('treasurer');
        $bank = TransactionType::where('code', 'bank_income')->firstOrFail();
        $this->actingAs($treasurer)->get(route('approvals.index'))->assertInertia(fn (Assert $page) => $page->where('types', fn ($types) => collect($types)->contains('code', 'bank_income')));
        $this->actingAs($treasurer)->post(route('approvals.store'), [
            'transaction_type_id' => $bank->id, 'title' => 'Intérêts T1', 'amount' => 18, 'fund_account_id' => $this->account('treasury')->id,
        ])->assertSessionHasNoErrors();
        $flow = app(FundFlowService::class)->sankey(['from' => CarbonImmutable::today()->subYear(), 'to' => CarbonImmutable::today()->endOfDay()]);
        $this->assertSame('Intérêts et revenus bancaires', $flow['breakdown']['in'][0]['label']);
    }
}
