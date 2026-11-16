<?php

namespace Tests\Feature;

use App\Enums\FundMovementStatus;
use App\Enums\FundMovementType;
use App\Enums\ProjectSector;
use App\Enums\ProjectStatus;
use App\Models\FinancialOperation;
use App\Models\FundAccount;
use App\Models\FundMovement;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FundAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProjectFundingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            CountrySeeder::class,
            FundAccountSeeder::class,
        ]);
        // Ces tests portent sur le financement lui-même : une seule validation suffit ici
        // (le circuit à deux validations est testé dans RegulationWorkflowTest).
        \App\Models\TransactionType::query()->update(['required_approvals' => 1]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function creditFamilyProjects(float $amount = 100000): FundAccount
    {
        $account = FundAccount::where('slug', 'family-projects')->firstOrFail();

        FundMovement::create([
            'fund_account_id' => $account->id,
            'type' => FundMovementType::ManualIncome,
            'status' => FundMovementStatus::Validated,
            'amount' => $amount,
            'currency' => 'USD',
            'direction' => 'credit',
            'label' => 'Crédit test poche projets',
            'movement_date' => now()->toDateString(),
            'validated_at' => now(),
        ]);

        $account->update(['current_balance' => $amount]);

        return $account->fresh();
    }

    private function approvedProject(User $owner, float $requested = 40000): Project
    {
        return Project::create([
            'owner_id' => $owner->id,
            'title' => 'Projet à financer',
            'slug' => 'projet-a-financer-'.uniqid(),
            'sector' => ProjectSector::Agriculture,
            'total_budget' => 100000,
            'personal_contribution' => 60000,
            'requested_amount' => $requested,
            'currency' => 'USD',
            'status' => ProjectStatus::ApprovedByFamily,
            'created_by' => $owner->id,
        ]);
    }

    public function test_treasurer_can_fund_approved_project(): void
    {
        $owner = $this->userWithRole('member');
        $treasurer = $this->userWithRole('treasurer');
        $account = $this->creditFamilyProjects(100000);
        $project = $this->approvedProject($owner, 40000);

        $response = $this->actingAs($treasurer)->patch(route('projects.fund', $project));

        $response->assertRedirect(route('projects.show', $project));

        $project->refresh();
        $this->assertSame(ProjectStatus::Funded, $project->status);
        $this->assertNotNull($project->funded_at);
        $this->assertSame($treasurer->id, $project->funded_by);
        $this->assertEquals(40000.0, (float) $project->funded_amount);

        $this->assertDatabaseHas('fund_movements', [
            'project_id' => $project->id,
            'fund_account_id' => $account->id,
            'type' => FundMovementType::ProjectFunding->value,
            'direction' => 'debit',
            'amount' => 40000,
            'status' => FundMovementStatus::Validated->value,
        ]);

        $this->assertEquals(60000.0, (float) $account->fresh()->current_balance);

        $this->assertTrue(
            Activity::query()
                ->where('event', 'project.funded')
                ->where('subject_id', $project->id)
                ->exists()
        );
    }

    public function test_president_can_fund_approved_project(): void
    {
        $owner = $this->userWithRole('member');
        $president = $this->userWithRole('president');
        $this->creditFamilyProjects();
        $project = $this->approvedProject($owner);

        $this->actingAs($president)->patch(route('projects.fund', $project))->assertRedirect();

        $this->assertSame(ProjectStatus::Funded, $project->fresh()->status);
    }

    public function test_member_cannot_fund_project(): void
    {
        $owner = $this->userWithRole('member');
        $this->creditFamilyProjects();
        $project = $this->approvedProject($owner);

        $this->actingAs($owner)->patch(route('projects.fund', $project))->assertForbidden();
        $this->assertSame(ProjectStatus::ApprovedByFamily, $project->fresh()->status);
    }

    public function test_cannot_fund_project_not_approved_by_family(): void
    {
        $owner = $this->userWithRole('member');
        $treasurer = $this->userWithRole('treasurer');
        $this->creditFamilyProjects();

        $project = $this->approvedProject($owner);
        $project->update(['status' => ProjectStatus::ApprovedByCommittee]);

        $this->actingAs($treasurer)->patch(route('projects.fund', $project))->assertForbidden();
    }

    public function test_funding_retry_does_not_debit_twice(): void
    {
        $owner = $this->userWithRole('member');
        $treasurer = $this->userWithRole('treasurer');
        $this->creditFamilyProjects();
        $project = $this->approvedProject($owner);

        $payload = ['idempotency_key' => 'project-funding-retry-test'];
        $this->actingAs($treasurer)->patch(route('projects.fund', $project), $payload)->assertRedirect();
        $this->actingAs($treasurer)->patch(route('projects.fund', $project), $payload)->assertRedirect();

        $this->assertSame(1, FundMovement::query()
            ->where('project_id', $project->id)
            ->where('type', FundMovementType::ProjectFunding)
            ->count());
        // Le second clic est refusé : le projet est déjà financé, une seule opération existe.
        $this->assertSame(1, FinancialOperation::where('idempotency_key', 'like', 'approval-%')->count());
        $this->assertSame('60000.00', FundAccount::where('slug', 'family-projects')->value('current_balance'));
    }

    public function test_cannot_fund_with_insufficient_balance(): void
    {
        $owner = $this->userWithRole('member');
        $treasurer = $this->userWithRole('treasurer');
        $this->creditFamilyProjects(1000);
        $project = $this->approvedProject($owner, 40000);

        $response = $this->actingAs($treasurer)->patch(route('projects.fund', $project));

        $response->assertRedirect();
        $response->assertSessionHasErrors('fund');
        $this->assertSame(ProjectStatus::ApprovedByFamily, $project->fresh()->status);
        $this->assertDatabaseMissing('fund_movements', [
            'project_id' => $project->id,
            'type' => FundMovementType::ProjectFunding->value,
        ]);
        $this->assertSame(0, FinancialOperation::where('operation_type', 'project_funding')
            ->where('idempotency_key', "project-funding:{$project->id}")
            ->count());
    }

    public function test_project_funding_movement_cannot_be_cancelled(): void
    {
        $owner = $this->userWithRole('member');
        $treasurer = $this->userWithRole('treasurer');
        $this->creditFamilyProjects();
        $project = $this->approvedProject($owner);

        $this->actingAs($treasurer)->patch(route('projects.fund', $project))->assertRedirect();

        $movement = FundMovement::query()
            ->where('project_id', $project->id)
            ->where('type', FundMovementType::ProjectFunding)
            ->firstOrFail();

        $response = $this->actingAs($treasurer)->patch(route('funds.movements.cancel', $movement), [
            'cancellation_reason' => 'Tentative d\'annulation',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('cancellation_reason');
        $this->assertSame(FundMovementStatus::Validated, $movement->fresh()->status);
    }
}
