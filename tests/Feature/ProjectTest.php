<?php

namespace Tests\Feature;

use App\Enums\ProjectSector;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            CountrySeeder::class,
            \Database\Seeders\FundAccountSeeder::class,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function validProjectData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Projet test',
            'short_description' => 'Résumé court',
            'description' => 'Description détaillée',
            'sector' => ProjectSector::Agriculture->value,
            'total_budget' => 100000,
            'personal_contribution' => 60000,
            'requested_amount' => 40000,
            'currency' => 'USD',
            'fund_account_id' => \App\Models\FundAccount::where('slug', 'family-projects')->value('id'),
        ], $overrides);
    }

    private function createProject(User $owner, array $overrides = []): Project
    {
        return Project::create([
            ...$this->validProjectData(),
            'owner_id' => $owner->id,
            'slug' => 'projet-test-'.uniqid(),
            'status' => ProjectStatus::Draft,
            'created_by' => $owner->id,
            ...$overrides,
        ]);
    }

    public function test_member_can_create_draft_project(): void
    {
        $member = $this->userWithRole('member');

        $response = $this->actingAs($member)->post(route('projects.store'), $this->validProjectData());

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'owner_id' => $member->id,
            'title' => 'Projet test',
            'status' => ProjectStatus::Draft->value,
        ]);
    }

    public function test_member_cannot_request_more_than_50_percent(): void
    {
        $member = $this->userWithRole('member');

        $response = $this->actingAs($member)->post(route('projects.store'), $this->validProjectData([
            'total_budget' => 100000,
            'personal_contribution' => 40000,
            'requested_amount' => 60000,
        ]));

        $response->assertSessionHasErrors('requested_amount');
    }

    public function test_member_must_contribute_minimum_50_percent(): void
    {
        $member = $this->userWithRole('member');

        $response = $this->actingAs($member)->post(route('projects.store'), $this->validProjectData([
            'total_budget' => 100000,
            'personal_contribution' => 30000,
            'requested_amount' => 40000,
        ]));

        $response->assertSessionHasErrors('requested_amount');
    }

    public function test_member_can_submit_own_project(): void
    {
        $member = $this->userWithRole('member');
        $project = $this->createProject($member);

        $response = $this->actingAs($member)->patch(route('projects.submit', $project));

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::Submitted->value,
        ]);
    }

    public function test_member_cannot_modify_submitted_project(): void
    {
        $member = $this->userWithRole('member');
        $project = $this->createProject($member, ['status' => ProjectStatus::Submitted]);

        $response = $this->actingAs($member)->put(route('projects.update', $project), $this->validProjectData([
            'title' => 'Titre modifié',
        ]));

        $response->assertForbidden();
    }

    public function test_investment_committee_can_evaluate_project(): void
    {
        $committee = $this->userWithRole('investment_committee');
        $member = $this->userWithRole('member');
        $project = $this->createProject($member, ['status' => ProjectStatus::Submitted]);

        $response = $this->actingAs($committee)->post(route('projects.evaluate', $project), [
            'profitability_score' => 15,
            'risk_score' => 14,
            'founder_score' => 16,
            'family_impact_score' => 17,
            'sustainability_score' => 13,
            'values_alignment_score' => 18,
            'comment' => 'Bon projet',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_evaluations', [
            'project_id' => $project->id,
            'evaluator_id' => $committee->id,
            'total_score' => 93,
        ]);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::UnderReview->value,
        ]);
    }

    public function test_committee_can_approve_project(): void
    {
        $committee = $this->userWithRole('investment_committee');
        $member = $this->userWithRole('member');
        $project = $this->createProject($member, ['status' => ProjectStatus::UnderReview]);

        $response = $this->actingAs($committee)->patch(route('projects.approve-by-committee', $project), [
            'committee_decision_comment' => 'Projet validé',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::ApprovedByCommittee->value,
        ]);
    }

    public function test_committee_can_reject_project_with_reason(): void
    {
        $committee = $this->userWithRole('investment_committee');
        $member = $this->userWithRole('member');
        $project = $this->createProject($member, ['status' => ProjectStatus::UnderReview]);

        $response = $this->actingAs($committee)->patch(route('projects.reject-by-committee', $project), [
            'rejected_reason' => 'Budget insuffisamment détaillé pour validation.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::RejectedByCommittee->value,
        ]);
    }

    public function test_only_president_or_super_admin_can_submit_to_vote(): void
    {
        $member = $this->userWithRole('member');
        $committee = $this->userWithRole('investment_committee');
        $president = $this->userWithRole('president');
        $project = $this->createProject($member, ['status' => ProjectStatus::ApprovedByCommittee]);

        $this->actingAs($member)->patch(route('projects.submit-to-vote', $project))->assertForbidden();
        $this->actingAs($committee)->patch(route('projects.submit-to-vote', $project))->assertForbidden();

        $this->actingAs($president)->patch(route('projects.submit-to-vote', $project))->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::SubmittedToVote->value,
        ]);
    }

    public function test_cannot_submit_unapproved_project_to_vote(): void
    {
        $president = $this->userWithRole('president');
        $member = $this->userWithRole('member');
        $project = $this->createProject($member, ['status' => ProjectStatus::UnderReview]);

        $this->actingAs($president)->patch(route('projects.submit-to-vote', $project))->assertForbidden();
    }

    public function test_project_documents_are_private(): void
    {
        Storage::fake('local');

        $owner = $this->userWithRole('member');
        $other = $this->userWithRole('member');
        $project = $this->createProject($owner, ['status' => ProjectStatus::Draft]);

        $file = UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf');
        $this->actingAs($owner)->post(route('projects.documents.store', $project), [
            'type' => 'business_plan',
            'document' => $file,
        ])->assertRedirect();

        $document = ProjectDocument::first();
        Storage::disk('local')->assertExists($document->file_path);

        $this->actingAs($other)->get(route('projects.documents.download', $document))->assertForbidden();
    }

    public function test_third_party_member_cannot_download_project_document(): void
    {
        $owner = $this->userWithRole('member');
        $other = $this->userWithRole('member');
        $project = $this->createProject($owner);

        $document = ProjectDocument::create([
            'project_id' => $project->id,
            'type' => 'business_plan',
            'original_name' => 'plan.pdf',
            'file_name' => 'plan.pdf',
            'file_path' => 'project-documents/plan.pdf',
            'uploaded_by' => $owner->id,
        ]);

        Storage::fake('local');
        Storage::disk('local')->put($document->file_path, 'contenu test');

        $this->actingAs($other)->get(route('projects.documents.download', $document))->assertForbidden();
        $this->actingAs($owner)->get(route('projects.documents.download', $document))->assertOk();
    }

    public function test_activity_log_on_project_submission(): void
    {
        $member = $this->userWithRole('member');
        $project = $this->createProject($member);

        $this->actingAs($member)->patch(route('projects.submit', $project));

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'event' => 'project.submitted',
        ]);

        $activity = Activity::where('subject_id', $project->id)
            ->where('event', 'project.submitted')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Projet soumis pour analyse', $activity->description);
    }
}
