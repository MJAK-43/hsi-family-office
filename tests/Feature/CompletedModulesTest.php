<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\ProjectSector;
use App\Enums\ProjectStatus;
use App\Enums\VoteStatus;
use App\Models\ContributionPlan;
use App\Models\Country;
use App\Models\MemberProfile;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ContributionDueReminder;
use App\Services\ContributionDueService;
use App\Services\ProjectService;
use App\Services\VoteService;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class CompletedModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, CountrySeeder::class]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_funded_project_can_be_followed_until_completion(): void
    {
        $owner = $this->user('member');
        $manager = $this->user('president');
        $project = Project::create([
            'owner_id' => $owner->id, 'title' => 'Projet suivi', 'slug' => 'projet-suivi',
            'sector' => ProjectSector::Technology, 'total_budget' => 1000, 'personal_contribution' => 500,
            'requested_amount' => 500, 'currency' => 'USD', 'status' => ProjectStatus::Funded,
            'funded_at' => now(), 'funded_amount' => 500, 'created_by' => $owner->id,
        ]);
        $service = app(ProjectService::class);
        $service->addMilestone($project, $manager, ['title' => 'Prototype', 'due_date' => now()->addMonth()->toDateString(), 'planned_amount' => 200]);
        $project = $service->start($project, $manager);
        $service->addProgressReport($project, $owner, [
            'progress_percent' => 60, 'spent_amount' => 150, 'summary' => 'Le prototype est opérationnel.',
            'risks' => null, 'next_steps' => 'Déploiement', 'reported_at' => now()->toDateString(),
        ]);
        $project = $service->complete($project->fresh(), $manager, 'Objectifs atteints et livrables validés.');

        $this->assertSame(ProjectStatus::Completed, $project->status);
        $this->assertSame(100, $project->progress_percent);
        $this->assertCount(1, $project->milestones);
        $this->assertCount(1, $project->progressReports);
    }

    public function test_scheduled_vote_uses_custom_options_and_excludes_confirmed_conflict(): void
    {
        $president = $this->user('president');
        $eligible = $this->user('member');
        $excluded = $this->user('member');
        $service = app(VoteService::class);
        $vote = $service->create([
            'title' => 'Choix stratégique', 'type' => 'governance', 'status' => VoteStatus::Scheduled->value,
            'opens_at' => now()->subMinute(), 'closes_at' => now()->addDay(), 'options' => ['Option A', 'Option B'],
            'allow_abstention' => false, 'ballot_visibility' => 'secret',
        ], $president);
        $conflict = $service->declareConflict($vote, $excluded, $excluded, 'Participation directe au dossier');
        $service->resolveConflict($conflict, $president, true, 'Conflit confirmé');
        $this->assertSame(1, $service->openScheduled());

        $vote->refresh();
        $this->assertSame(VoteStatus::Open, $vote->status);
        $this->assertSame(['Option A', 'Option B'], $vote->options()->pluck('label')->all());
        $service->castBallot($vote, $eligible, 'option_1');
        $this->expectException(RuntimeException::class);
        $service->castBallot($vote, $excluded, 'option_2');
    }

    public function test_contribution_dues_are_generated_marked_late_and_reminded(): void
    {
        Notification::fake();
        $country = Country::query()->firstOrFail();
        $member = $this->user('member');
        MemberProfile::create(['user_id' => $member->id, 'country_id' => $country->id, 'status' => MemberStatus::Active]);
        ContributionPlan::create([
            'name' => 'Mensuel automatique', 'amount' => 50, 'currency' => 'USD', 'frequency' => 'monthly',
            'country_id' => $country->id, 'is_active' => true, 'auto_generate' => true, 'due_day' => 5, 'grace_days' => 2,
        ]);
        // Ce test porte sur un plan mensuel : la contribution trimestrielle du règlement est mise de côté.
        \App\Models\ContributionPlan::where('is_regulatory', true)->update(['auto_generate' => false]);
        $service = app(ContributionDueService::class);
        $this->assertSame(1, $service->generate(CarbonImmutable::parse('2026-09-01')));
        $result = $service->markLateAndRemind(CarbonImmutable::parse('2026-09-08'));

        $this->assertSame(['late' => 1, 'reminded' => 1], $result);
        $this->assertDatabaseHas('contribution_dues', ['user_id' => $member->id, 'period_key' => '2026-09', 'status' => 'late']);
        Notification::assertSentTo($member, ContributionDueReminder::class);
    }
}
