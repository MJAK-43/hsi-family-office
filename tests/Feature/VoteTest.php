<?php

namespace Tests\Feature;

use App\Enums\ProjectSector;
use App\Enums\ProjectStatus;
use App\Enums\VoteResult;
use App\Enums\VoteStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Services\VoteService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class VoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            CountrySeeder::class,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function projectReadyForVote(User $owner): Project
    {
        return Project::create([
            'owner_id' => $owner->id,
            'title' => 'Projet vote test',
            'slug' => 'projet-vote-'.uniqid(),
            'sector' => ProjectSector::Technology,
            'total_budget' => 100000,
            'personal_contribution' => 60000,
            'requested_amount' => 40000,
            'currency' => 'USD',
            'status' => ProjectStatus::ApprovedByCommittee,
            'created_by' => $owner->id,
        ]);
    }

    public function test_submit_project_to_vote_creates_open_vote(): void
    {
        $president = $this->userWithRole('president');
        $owner = $this->userWithRole('member');
        $project = $this->projectReadyForVote($owner);

        $this->actingAs($president)->patch(route('projects.submit-to-vote', $project))->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::SubmittedToVote->value,
        ]);
        $this->assertDatabaseHas('votes', [
            'project_id' => $project->id,
            'status' => VoteStatus::Open->value,
        ]);
    }

    public function test_member_can_cast_ballot(): void
    {
        $president = $this->userWithRole('president');
        $member = $this->userWithRole('member');
        $owner = $this->userWithRole('member');
        $project = $this->projectReadyForVote($owner);
        $project->update(['status' => ProjectStatus::SubmittedToVote]);

        $vote = app(VoteService::class)->openForProject($project, $president);

        $this->actingAs($member)->post(route('votes.cast', $vote), [
            'option' => 'for',
            'comment' => 'Je soutiens',
        ])->assertRedirect();

        $this->assertDatabaseHas('vote_ballots', [
            'vote_id' => $vote->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_member_cannot_vote_twice(): void
    {
        $president = $this->userWithRole('president');
        $member = $this->userWithRole('member');
        $owner = $this->userWithRole('member');
        $project = $this->projectReadyForVote($owner);
        $project->update(['status' => ProjectStatus::SubmittedToVote]);
        $vote = app(VoteService::class)->openForProject($project, $president);

        $this->actingAs($member)->post(route('votes.cast', $vote), ['option' => 'for']);
        $this->actingAs($member)->post(route('votes.cast', $vote), ['option' => 'against'])
            ->assertSessionHasErrors('option');
    }

    public function test_closing_vote_approves_project_when_majority_for(): void
    {
        $president = $this->userWithRole('president');
        $members = collect([
            $this->userWithRole('member'),
            $this->userWithRole('member'),
            $this->userWithRole('member'),
        ]);
        $owner = $this->userWithRole('member');
        $project = $this->projectReadyForVote($owner);
        $project->update(['status' => ProjectStatus::SubmittedToVote]);

        $vote = app(VoteService::class)->openForProject($project, $president);
        $vote->update(['eligible_count' => 3, 'quorum_percent' => 50]);

        foreach ($members as $i => $member) {
            // Règlement : 80 % des voix pour et contre ; l'abstention n'est pas comptée.
            app(VoteService::class)->castBallot($vote, $member, $i === 2 ? 'abstain' : 'for');
        }

        $this->actingAs($president)->patch(route('votes.close', $vote))->assertRedirect();

        $this->assertDatabaseHas('votes', [
            'id' => $vote->id,
            'status' => VoteStatus::Closed->value,
            'result' => VoteResult::Approved->value,
        ]);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::ApprovedByFamily->value,
        ]);
    }

    public function test_closing_vote_rejects_project_when_majority_against(): void
    {
        $president = $this->userWithRole('president');
        $members = collect([
            $this->userWithRole('member'),
            $this->userWithRole('member'),
            $this->userWithRole('member'),
        ]);
        $owner = $this->userWithRole('member');
        $project = $this->projectReadyForVote($owner);
        $project->update(['status' => ProjectStatus::SubmittedToVote]);

        $vote = app(VoteService::class)->openForProject($project, $president);
        $vote->update(['eligible_count' => 3, 'quorum_percent' => 50]);

        foreach ($members as $member) {
            app(VoteService::class)->castBallot($vote, $member, 'against');
        }

        app(VoteService::class)->close($vote->fresh(), $president);

        $this->assertEquals(ProjectStatus::RejectedByFamily, $project->fresh()->status);
        $this->assertEquals(VoteResult::Rejected, $vote->fresh()->result);
    }

    public function test_no_quorum_keeps_project_submitted_to_vote(): void
    {
        $president = $this->userWithRole('president');
        $member = $this->userWithRole('member');
        $owner = $this->userWithRole('member');
        $project = $this->projectReadyForVote($owner);
        $project->update(['status' => ProjectStatus::SubmittedToVote]);

        $vote = app(VoteService::class)->openForProject($project, $president);
        $vote->update(['eligible_count' => 10, 'quorum_percent' => 50]);

        app(VoteService::class)->castBallot($vote, $member, 'for');
        app(VoteService::class)->close($vote->fresh(), $president);

        $this->assertEquals(VoteResult::NoQuorum, $vote->fresh()->result);
        $this->assertEquals(ProjectStatus::SubmittedToVote, $project->fresh()->status);
    }

    public function test_guest_cannot_cast_vote(): void
    {
        $president = $this->userWithRole('president');
        $guest = $this->userWithRole('guest');
        $owner = $this->userWithRole('member');
        $project = $this->projectReadyForVote($owner);
        $project->update(['status' => ProjectStatus::SubmittedToVote]);
        $vote = app(VoteService::class)->openForProject($project, $president);

        $this->actingAs($guest)->post(route('votes.cast', $vote), ['option' => 'for'])->assertForbidden();
    }

    public function test_only_president_can_close_vote(): void
    {
        $president = $this->userWithRole('president');
        $member = $this->userWithRole('member');
        $owner = $this->userWithRole('member');
        $project = $this->projectReadyForVote($owner);
        $project->update(['status' => ProjectStatus::SubmittedToVote]);
        $vote = app(VoteService::class)->openForProject($project, $president);

        $this->actingAs($member)->patch(route('votes.close', $vote))->assertForbidden();
        $this->actingAs($president)->patch(route('votes.close', $vote))->assertRedirect();
    }

    public function test_activity_log_on_ballot_cast(): void
    {
        $president = $this->userWithRole('president');
        $member = $this->userWithRole('member');
        $owner = $this->userWithRole('member');
        $project = $this->projectReadyForVote($owner);
        $project->update(['status' => ProjectStatus::SubmittedToVote]);
        $vote = app(VoteService::class)->openForProject($project, $president);

        $this->actingAs($member)->post(route('votes.cast', $vote), ['option' => 'for']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Vote::class,
            'subject_id' => $vote->id,
            'event' => 'vote.ballot_cast',
        ]);
    }

    public function test_member_can_view_votes_index(): void
    {
        $member = $this->userWithRole('member');

        $this->actingAs($member)->get(route('votes.index'))->assertOk();
    }
}
