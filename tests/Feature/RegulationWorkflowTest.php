<?php

namespace Tests\Feature;

use App\Enums\ContributionDueStatus;
use App\Enums\ContributionPaymentStatus;
use App\Enums\FundMovementType;
use App\Enums\PaymentMethod;
use App\Enums\ProjectStatus;
use App\Enums\VoteResult;
use App\Enums\VoteStatus;
use App\Models\ApplicationSetting;
use App\Models\ApprovalRequest;
use App\Models\ContributionDue;
use App\Models\ContributionPayment;
use App\Models\ContributionPlan;
use App\Models\FundAccount;
use App\Models\LoanInstallment;
use App\Models\MemberProfile;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use App\Models\Vote;
use App\Models\WithdrawalRequest;
use App\Services\ContributionDueService;
use App\Services\FundService;
use App\Services\LoanService;
use App\Services\VoteService;
use App\Support\FundRules;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FundAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegulationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FundRules::flush();
        $this->seed([RolePermissionSeeder::class, CountrySeeder::class, FundAccountSeeder::class]);
        $this->setRule('loans_open_on', '2020-01-01');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        FundRules::flush();
        parent::tearDown();
    }

    private function setRule(string $key, string $value): void
    {
        ApplicationSetting::updateOrCreate(['key' => $key], ['group' => 'règlement du fonds', 'label' => $key, 'type' => FundRules::DEFINITIONS[$key]['type'], 'value' => $value]);
        FundRules::flush();
    }

    private function user(string ...$roles): User
    {
        $user = User::factory()->create();
        $user->syncRoles($roles ?: ['member']);
        MemberProfile::create(['user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    /** Membre qui cotise depuis 7 mois (2 versements validés). */
    private function contributor(string ...$roles): User
    {
        $user = $this->user(...($roles ?: ['member']));
        foreach ([7, 4] as $monthsAgo) {
            ContributionPayment::create([
                'user_id' => $user->id, 'amount' => 75, 'currency' => 'USD', 'payment_method' => PaymentMethod::BankTransfer,
                'status' => ContributionPaymentStatus::Validated, 'submitted_at' => now()->subMonths($monthsAgo),
                'paid_at' => now()->subMonths($monthsAgo), 'validated_at' => now()->subMonths($monthsAgo), 'created_by' => $user->id,
            ]);
        }

        return $user;
    }

    private function creditFund(float $amount = 10000): void
    {
        $account = FundAccount::where('slug', 'family-projects')->firstOrFail();
        app(FundService::class)->createMovement([
            'fund_account_id' => $account->id, 'type' => FundMovementType::ManualIncome, 'amount' => (string) $amount,
            'currency' => 'USD', 'direction' => 'credit', 'label' => 'Apport initial',
        ]);
    }

    private function loanType(): ProjectType
    {
        return ProjectType::where('code', 'loan')->firstOrFail();
    }

    private function loanPayload(array $overrides = []): array
    {
        return array_merge([
            'project_type_id' => $this->loanType()->id,
            'fund_account_id' => FundAccount::where('slug', 'family-projects')->value('id'),
            'title' => 'Achat d’un véhicule de travail',
            'description' => 'Véhicule pour mon activité de livraison.',
            'requested_amount' => 1200,
            'loan_duration_months' => 12,
            'repayment_plan' => '12 mensualités sur mon salaire.',
            'presentation_date' => now()->addDays(20)->toDateString(),
            'currency' => 'USD',
        ], $overrides);
    }

    private function submittedLoan(User $owner, array $overrides = []): Project
    {
        $this->actingAs($owner)->post(route('projects.store'), $this->loanPayload($overrides))->assertSessionHasNoErrors();
        $project = Project::where('owner_id', $owner->id)->latest('id')->firstOrFail();
        $this->actingAs($owner)->patch(route('projects.submit', $project))->assertSessionHasNoErrors();

        return $project->fresh();
    }

    private function validateAllCriteria(Project $project, User $committee): void
    {
        foreach ($project->criteria as $criterion) {
            $this->actingAs($committee)->patch(route('projects.criteria.check', $criterion), ['status' => 'validated', 'comment' => 'Vérifié'])->assertSessionHasNoErrors();
        }
    }

    public function test_project_types_and_criteria_are_managed_by_the_president_and_the_committee(): void
    {
        $committee = $this->user('investment_committee');
        $member = $this->user('member');

        $this->actingAs($committee)->post(route('project-types.store'), ['name' => 'Solidarité', 'is_loan' => true, 'requires_personal_contribution' => false, 'is_active' => true])->assertSessionHas('success');
        $type = ProjectType::where('name', 'Solidarité')->firstOrFail();
        $this->actingAs($committee)->post(route('project-types.criteria.store', $type), ['label' => 'Justificatif du besoin fourni', 'is_blocking' => true, 'is_active' => true])->assertSessionHas('success');
        $this->actingAs($committee)->post(route('project-types.criteria.store', $type), ['label' => 'Cotisations à jour', 'is_blocking' => true, 'auto_check' => 'contributions_up_to_date', 'is_active' => true])->assertSessionHas('success');
        $this->assertSame(['Justificatif du besoin fourni', 'Cotisations à jour'], $type->criteria()->pluck('label')->all());

        $this->actingAs($this->user('president'))->get(route('project-types.index'))->assertOk();
        $this->actingAs($member)->post(route('project-types.store'), ['name' => 'Interdit'])->assertForbidden();
    }

    public function test_the_applicant_file_shows_the_situation_and_the_automatic_checks(): void
    {
        $this->creditFund(10000);
        $owner = $this->contributor();
        $committee = $this->user('investment_committee');
        $project = $this->submittedLoan($owner);

        $this->assertSame(ProjectStatus::Submitted, $project->status);
        $this->assertCount(13, $project->criteria);
        $this->assertSame('validated', $project->criteria->firstWhere('auto_check', 'seniority')->status);
        $this->assertSame('validated', $project->criteria->firstWhere('auto_check', 'amount_within_fund_cap')->status);
        $this->assertSame('pending', $project->criteria->firstWhere('auto_check', null)->status);

        $this->actingAs($committee)->get(route('projects.show', $project))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('situation.contributions_count', 2)
                ->where('situation.contribution_months', 7)
                ->where('situation.ongoing_loans', 0)
                ->where('checks.amount_within_fund_cap.result', 'pass')
                ->where('can.review', true));

        // Le demandeur voit le détail de chaque critère, mais n'instruit pas son propre dossier.
        $this->actingAs($owner)->get(route('projects.show', $project))->assertOk()
            ->assertInertia(fn ($page) => $page->has('project.criteria', 13)->where('can.review', false)->where('checks', null));
        $this->actingAs($owner)->patch(route('projects.criteria.check', $project->criteria->first()), ['status' => 'validated'])->assertForbidden();
    }

    public function test_submission_is_blocked_by_an_ongoing_loan_or_a_recent_refusal(): void
    {
        $this->creditFund(10000);
        $owner = $this->contributor();
        Project::create([
            'owner_id' => $owner->id, 'project_type_id' => $this->loanType()->id, 'title' => 'Prêt précédent', 'sector' => 'other',
            'total_budget' => 500, 'personal_contribution' => 0, 'requested_amount' => 500, 'currency' => 'USD', 'status' => ProjectStatus::InProgress,
        ]);
        $this->actingAs($owner)->post(route('projects.store'), $this->loanPayload());
        $project = Project::where('title', 'Achat d’un véhicule de travail')->firstOrFail();
        $this->actingAs($owner)->patch(route('projects.submit', $project))->assertSessionHasErrors('submit');
        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);

        // Refus il y a 10 jours : autre projet possible seulement après 30 jours.
        Project::where('title', 'Prêt précédent')->update(['status' => ProjectStatus::RejectedByCommittee, 'decided_at' => now()->subDays(10)]);
        $this->actingAs($owner)->patch(route('projects.submit', $project))->assertSessionHasErrors('submit');
        Project::where('title', 'Prêt précédent')->update(['decided_at' => now()->subDays(31)]);
        $this->actingAs($owner)->patch(route('projects.submit', $project))->assertSessionHasNoErrors();
        $this->assertSame(ProjectStatus::Submitted, $project->fresh()->status);
    }

    public function test_the_committee_checks_criteria_requests_information_and_decides_with_a_reason(): void
    {
        $this->creditFund(10000);
        $owner = $this->contributor();
        $committee = $this->user('investment_committee');
        $project = $this->submittedLoan($owner);

        // Acceptation impossible tant qu'un critère bloquant n'est pas validé.
        $this->actingAs($committee)->patch(route('projects.decide', $project), ['decision' => 'accept', 'reason' => 'Dossier complet'])->assertSessionHasErrors('decision');

        $this->actingAs($committee)->patch(route('projects.decide', $project), ['decision' => 'information', 'reason' => 'Ajoutez le devis du véhicule.'])->assertSessionHas('success');
        $project->refresh();
        $this->assertSame(ProjectStatus::NeedsInformation, $project->status);
        Notification::assertSentTo($owner, \App\Notifications\FundNotification::class);

        // Le demandeur complète puis redépose ; les vérifications du comité restent.
        $this->actingAs($owner)->post(route('projects.documents.store', $project), [
            'type' => 'quote', 'document' => \Illuminate\Http\UploadedFile::fake()->create('devis.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('projects.submit', $project))->assertSessionHasNoErrors();
        $this->assertSame(ProjectStatus::Submitted, $project->fresh()->status);

        $this->validateAllCriteria($project->fresh('criteria'), $committee);
        $this->actingAs($committee)->patch(route('projects.decide', $project), ['decision' => 'accept', 'reason' => 'Projet solide et plan réaliste.'])->assertSessionHas('success');
        $project->refresh();
        $this->assertSame(ProjectStatus::ApprovedByCommittee, $project->status);
        $this->assertSame('Projet solide et plan réaliste.', $project->committee_decision_comment);
        $this->assertDatabaseHas('activity_log', ['event' => 'project.criterion.checked', 'causer_id' => $committee->id]);
    }

    public function test_a_committee_document_can_stay_internal(): void
    {
        $this->creditFund(10000);
        $owner = $this->contributor();
        $committee = $this->user('investment_committee');
        $project = $this->submittedLoan($owner);

        $this->actingAs($committee)->post(route('projects.documents.store', $project), [
            'type' => 'other', 'title' => 'Note d’analyse', 'visibility' => 'committee',
            'document' => \Illuminate\Http\UploadedFile::fake()->create('analyse.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $document = $project->documents()->firstOrFail();
        $this->assertSame('committee', $document->visibility);

        $this->actingAs($owner)->get(route('projects.show', $project))->assertInertia(fn ($page) => $page->has('project.documents', 0));
        $this->actingAs($owner)->get(route('projects.documents.download', $document))->assertForbidden();
        $this->actingAs($committee)->get(route('projects.show', $project))->assertInertia(fn ($page) => $page->has('project.documents', 1));
    }

    public function test_the_vote_can_be_scheduled_excludes_the_applicant_and_needs_80_percent(): void
    {
        $this->creditFund(10000);
        $owner = $this->contributor();
        $president = $this->user('president');
        $committee = $this->user('investment_committee');
        $voters = collect(range(1, 4))->map(fn () => $this->user('member'));
        $project = $this->submittedLoan($owner);
        $this->validateAllCriteria($project->fresh('criteria'), $committee);
        $this->actingAs($committee)->patch(route('projects.decide', $project), ['decision' => 'accept', 'reason' => 'Conforme.']);

        // Vote programmé dans 2 jours, dates modifiables.
        $opens = now('Europe/Paris')->addDays(2)->format('Y-m-d\TH:i');
        $closes = now('Europe/Paris')->addDays(9)->format('Y-m-d\TH:i');
        $this->actingAs($president)->patch(route('projects.submit-to-vote', $project), ['opens_at' => $opens, 'closes_at' => $closes])->assertSessionHasNoErrors();
        $vote = Vote::where('project_id', $project->id)->firstOrFail();
        $this->assertSame(VoteStatus::Scheduled, $vote->status);
        $this->actingAs($president)->patch(route('votes.reschedule', $vote), ['opens_at' => $opens, 'closes_at' => now('Europe/Paris')->addDays(12)->format('Y-m-d\TH:i')])->assertSessionHas('success');

        $this->travel(3)->days();
        app(VoteService::class)->openScheduled();
        $vote->refresh();
        $this->assertSame(VoteStatus::Open, $vote->status);
        $this->assertFalse($vote->eligibilities()->where('user_id', $owner->id)->value('is_eligible'));

        // 3 pour, 1 contre = 75 % < 80 % : refusé.
        foreach ($voters as $i => $voter) app(VoteService::class)->castBallot($vote, $voter, $i === 3 ? 'against' : 'for');
        app(VoteService::class)->castBallot($vote, $president, 'abstain');
        $closed = app(VoteService::class)->close($vote, $president);
        $this->assertSame(VoteResult::Rejected, $closed->result);
        $this->assertSame(ProjectStatus::RejectedByFamily, $project->fresh()->status);
        $this->assertNotNull($project->fresh()->decided_at);
    }

    public function test_only_the_administrator_sees_who_voted_what(): void
    {
        $owner = $this->contributor();
        $project = Project::create(['owner_id' => $owner->id, 'title' => 'Projet', 'sector' => 'other', 'total_budget' => 100, 'personal_contribution' => 50, 'requested_amount' => 50, 'currency' => 'USD', 'status' => ProjectStatus::ApprovedByCommittee]);
        $president = $this->user('president');
        $voter = $this->user('member');
        $project->update(['status' => ProjectStatus::SubmittedToVote]);
        $vote = app(VoteService::class)->openForProject($project, $president);
        app(VoteService::class)->castBallot($vote, $voter, 'for');

        $this->actingAs($president)->get(route('votes.show', $vote))->assertInertia(fn ($page) => $page->has('ballots', 0));
        $this->actingAs($this->user('super_admin'))->get(route('votes.show', $vote))->assertInertia(fn ($page) => $page->has('ballots', 1));
    }

    /** Prêt approuvé par le vote, prêt à être décaissé. */
    private function approvedLoan(User $owner, float $amount = 1200): Project
    {
        return Project::create([
            'owner_id' => $owner->id, 'project_type_id' => $this->loanType()->id, 'title' => 'Prêt atelier', 'sector' => 'other',
            'total_budget' => $amount, 'personal_contribution' => 0, 'requested_amount' => $amount, 'currency' => 'USD',
            'loan_duration_months' => 12, 'status' => ProjectStatus::ApprovedByFamily,
        ]);
    }

    public function test_the_disbursement_needs_two_treasurers_and_creates_the_schedule(): void
    {
        $this->creditFund(10000);
        $owner = $this->contributor();
        $first = $this->user('treasurer');
        $second = $this->user('treasurer');
        $project = $this->approvedLoan($owner);

        $this->actingAs($first)->post(route('projects.disburse', $project), [
            'amount' => 1200, 'disbursed_on' => now()->toDateString(), 'duration_months' => 12, 'first_due_date' => now()->addMonth()->toDateString(),
        ])->assertSessionHasNoErrors();
        $request = ApprovalRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame(1, $request->approvalsCount());
        $this->assertSame(ProjectStatus::ApprovedByFamily, $project->fresh()->status);

        // Même personne : refusé. Bénéficiaire : refusé.
        $this->actingAs($first)->patch(route('approvals.approve', $request))->assertSessionHasErrors('approval');
        $owner->assignRole('treasurer');
        $this->actingAs($owner)->patch(route('approvals.approve', $request))->assertSessionHasErrors('approval');

        $this->actingAs($second)->patch(route('approvals.approve', $request))->assertSessionHasNoErrors();
        $this->assertSame('executed', $request->fresh()->status);
        $project->refresh();
        $this->assertSame(ProjectStatus::InProgress, $project->status);
        $this->assertCount(12, $project->installments);
        // 4 % annuel sur 12 mois : environ 102,18 $ par mois, 1 226,19 $ au total.
        $this->assertEqualsWithDelta(102.18, (float) $project->installments->first()->amount, 0.02);
        $this->assertEqualsWithDelta(1226.19, (float) $project->loan_total_due, 0.1);
        $this->assertSame('8800.00', FundAccount::where('slug', 'family-projects')->value('current_balance'));
    }

    public function test_the_disbursement_respects_the_cap_of_half_the_fund_balance(): void
    {
        $this->creditFund(1000);
        $owner = $this->contributor();
        $project = $this->approvedLoan($owner, 900);

        $this->actingAs($this->user('treasurer'))->post(route('projects.disburse', $project), ['amount' => 900, 'duration_months' => 6])
            ->assertSessionHasErrors('fund');
        $this->assertSame(0, ApprovalRequest::count());
    }

    public function test_repayments_penalties_and_the_three_reminders(): void
    {
        $this->creditFund(10000);
        \App\Models\TransactionType::where('code', 'loan_disbursement')->update(['required_approvals' => 1]);
        $owner = $this->contributor();
        $treasurer = $this->user('treasurer');
        $members = collect(range(1, 2))->map(fn () => $this->user('member'));
        $president = $this->user('president');
        $project = $this->approvedLoan($owner);
        $this->actingAs($treasurer)->post(route('projects.disburse', $project), ['amount' => 1200, 'duration_months' => 12, 'first_due_date' => '2026-12-01', 'disbursed_on' => '2026-11-01']);
        $project->refresh();
        $first = $project->installments->first();

        // 11 jours de retard : pénalité de 1 % et 1er rappel au demandeur et aux trésoriers.
        $loans = app(LoanService::class);
        $loans->processDaily(CarbonImmutable::parse('2026-12-12'));
        $first->refresh();
        $this->assertSame('late', $first->status);
        $this->assertEqualsWithDelta(round((float) $first->amount * 0.01, 2), (float) $first->penalty_amount, 0.001);
        $this->assertSame(1, $first->reminder_level);
        Notification::assertSentTo([$owner, $treasurer], \App\Notifications\FundNotification::class);

        $loans->processDaily(CarbonImmutable::parse('2026-12-16'));
        $this->assertSame(2, $first->fresh()->reminder_level);
        Notification::assertSentTo($members->first(), \App\Notifications\FundNotification::class);

        // 32 jours : 3e rappel, décision collective préparée, 2e mois de pénalité entamé (depuis le 2 janvier).
        $loans->processDaily(CarbonImmutable::parse('2027-01-02'));
        $first->refresh();
        $this->assertSame(3, $first->reminder_level);
        $this->assertEqualsWithDelta(round((float) $first->amount * 0.02, 2), (float) $first->penalty_amount, 0.001);
        $this->assertDatabaseHas('votes', ['project_id' => $project->id, 'type' => 'governance', 'status' => 'draft']);

        // Remboursement : d'abord la pénalité, puis l'échéance.
        $this->actingAs($treasurer)->post(route('projects.repayments.store', $project), ['amount' => (float) $first->amount + (float) $first->penalty_amount, 'paid_on' => now()->toDateString()])->assertSessionHasNoErrors();
        $first->refresh();
        $this->assertSame('paid', $first->status);
        $this->assertEqualsWithDelta((float) $first->penalty_amount, (float) $first->penalty_paid, 0.001);
        // Le registre distingue capital remboursé, intérêts et pénalité, sur le compte qui a prêté.
        $legs = \App\Models\FundMovement::where('project_id', $project->id)->whereIn('type', ['loan_repayment', 'loan_interest', 'penalty_income'])->get()->keyBy(fn ($m) => $m->type->value);
        $this->assertEqualsWithDelta((float) $first->penalty_amount, (float) $legs['penalty_income']->amount, 0.001);
        $this->assertEqualsWithDelta((float) $first->interest, (float) $legs['loan_interest']->amount, 0.011);
        $this->assertEqualsWithDelta((float) $first->amount + (float) $first->penalty_amount, $legs->sum(fn ($m) => (float) $m->amount), 0.001);
        $this->assertSame($project->fund_account_id ?? FundAccount::where('slug', 'family-projects')->value('id'), $legs['loan_repayment']->fund_account_id);

        // Solde du prêt : le projet est clôturé et le membre peut redemander un prêt.
        $outstanding = $loans->outstanding($project->fresh('installments'));
        $this->actingAs($treasurer)->post(route('projects.repayments.store', $project), ['amount' => $outstanding, 'paid_on' => now()->toDateString()])->assertSessionHasNoErrors();
        $project->refresh();
        $this->assertSame(ProjectStatus::Completed, $project->status);
        $this->assertNotNull($project->repaid_at);
        $this->assertSame(0, app(\App\Services\EligibilityService::class)->ongoingLoans($owner)->count());
    }

    public function test_contribution_penalty_is_2_dollars_per_started_week_from_the_16th_and_carried_over(): void
    {
        $member = $this->user('member');
        $plan = ContributionPlan::create(['name' => 'Trimestrielle', 'amount' => 75, 'currency' => 'USD', 'frequency' => 'quarterly', 'due_day' => 15, 'grace_days' => 0, 'is_active' => true, 'auto_generate' => true]);
        $due = ContributionDue::create(['contribution_plan_id' => $plan->id, 'user_id' => $member->id, 'period_key' => '2027-Q1', 'period_label' => 'T1 2027', 'amount' => 75, 'currency' => 'USD', 'due_date' => '2027-01-15', 'status' => ContributionDueStatus::Pending]);
        $service = app(ContributionDueService::class);

        $this->assertSame(0.0, $service->penaltyFor($due, CarbonImmutable::parse('2027-01-15')));
        $this->assertSame(2.0, $service->penaltyFor($due, CarbonImmutable::parse('2027-01-16')));
        $this->assertSame(2.0, $service->penaltyFor($due, CarbonImmutable::parse('2027-01-22')));
        $this->assertSame(4.0, $service->penaltyFor($due, CarbonImmutable::parse('2027-01-23')));

        // Paiement reçu le 25 (2e semaine entamée) : pénalité figée à 4 $ puis reportée sur le trimestre suivant.
        $next = ContributionDue::create(['contribution_plan_id' => $plan->id, 'user_id' => $member->id, 'period_key' => '2027-Q2', 'period_label' => 'T2 2027', 'amount' => 75, 'currency' => 'USD', 'due_date' => '2027-04-15', 'status' => ContributionDueStatus::Pending]);
        $payment = ContributionPayment::create(['user_id' => $member->id, 'contribution_plan_id' => $plan->id, 'amount' => 75, 'currency' => 'USD', 'payment_method' => PaymentMethod::BankTransfer, 'status' => ContributionPaymentStatus::Validated, 'paid_at' => '2027-01-25', 'validated_at' => '2027-01-26', 'payment_year' => 2027, 'created_by' => $member->id]);
        $service->markPaid($payment);
        $this->assertSame('4.00', $due->fresh()->penalty_amount);
        $this->assertSame('4.00', $next->fresh()->carried_penalty);

        // Erreur bancaire justifiée : la pénalité est annulée et retirée du trimestre suivant.
        $this->actingAs($this->user('treasurer'))->patch(route('contributions.dues.waive-penalty', $due), ['reason' => 'Virement bloqué par la banque, justificatif reçu.'])->assertSessionHas('success');
        $this->assertTrue($due->fresh()->penalty_waived);
        $this->assertSame('0.00', $next->fresh()->carried_penalty);
    }

    public function test_a_member_can_leave_with_95_percent_refunded_on_a_validated_schedule(): void
    {
        $this->creditFund(5000);
        $member = $this->contributor();
        $manager = $this->user('treasurer');
        $second = $this->user('president');

        $this->actingAs($member)->post(route('withdrawals.store'), ['reason' => 'Je pars vivre à l’étranger.', 'confirm' => true])->assertSessionHas('success');
        $withdrawal = WithdrawalRequest::firstOrFail();
        $this->assertSame('142.50', $withdrawal->refund_amount); // 95 % de 150 $
        $this->assertSame(now()->addMonths(2)->toDateString(), $withdrawal->effective_date->toDateString());

        // La somme doit correspondre et les dates rester dans les 6 mois après le départ.
        $this->actingAs($manager)->put(route('withdrawals.schedule', $withdrawal), ['installments' => [['due_date' => now()->addMonths(3)->toDateString(), 'amount' => 100]]])->assertSessionHasErrors('installments');
        $this->actingAs($manager)->put(route('withdrawals.schedule', $withdrawal), ['installments' => [['due_date' => now()->addMonths(10)->toDateString(), 'amount' => 142.5]]])->assertSessionHasErrors('installments');
        $this->actingAs($manager)->put(route('withdrawals.schedule', $withdrawal), ['installments' => [
            ['due_date' => now()->addMonths(3)->toDateString(), 'amount' => 70], ['due_date' => now()->addMonths(4)->toDateString(), 'amount' => 72.5],
        ]])->assertSessionHas('success');
        $this->assertSame('scheduled', $withdrawal->fresh()->status);

        $account = FundAccount::where('slug', 'family-projects')->value('id');
        foreach ($withdrawal->fresh()->installments as $installment) {
            $this->actingAs($manager)->post(route('withdrawals.pay', $installment), ['fund_account_id' => $account])->assertSessionHas('success');
            $request = ApprovalRequest::where('subject_id', $installment->id)->firstOrFail();
            $this->assertSame('pending', $request->status); // 2 validations exigées pour un remboursement de départ
            $this->actingAs($second)->patch(route('approvals.approve', $request))->assertSessionHasNoErrors();
        }
        $this->assertSame('completed', $withdrawal->fresh()->status);
        $this->assertSame('inactive', $member->memberProfile->fresh()->status->value);
    }

    public function test_reports_exports_sharing_and_the_annual_audit(): void
    {
        $this->creditFund(3000);
        $this->contributor();
        $treasurer = $this->user('treasurer');
        $member = $this->user('member');
        $reviewer = $this->user('member');

        $this->actingAs($treasurer)->get(route('reports.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('full', true)->where('report.kpis.balance', 3000)->where('report.kpis.contributions_count', 2)->has('report.members'));
        $this->actingAs($member)->get(route('reports.index'))->assertOk()->assertInertia(fn ($page) => $page->where('full', false)->where('report', null));
        $this->actingAs($member)->get(route('reports.export', ['format' => 'xlsx']))->assertForbidden();

        $xlsx = $this->actingAs($treasurer)->get(route('reports.export', ['format' => 'xlsx', 'period' => 'month']));
        $xlsx->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $pdf = $this->actingAs($treasurer)->get(route('reports.export', ['format' => 'pdf', 'sections' => ['summary', 'statement']]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($treasurer)->post(route('reports.share'), ['title' => 'Rapport T4 2026', 'kind' => 'quarterly', 'from' => '2026-10-01', 'to' => '2026-12-31'])->assertSessionHas('success');
        $shared = \App\Models\SharedReport::firstOrFail();
        Notification::assertSentTo($member, \App\Notifications\FundNotification::class);
        $this->actingAs($member)->get(route('reports.shared', $shared))->assertOk();
        $this->actingAs($member)->get(route('reports.shared.pdf', $shared))->assertOk();

        // Les vérificateurs ne peuvent pas être des trésoriers.
        $this->actingAs($treasurer)->post(route('reports.audits.store'), ['year' => 2026, 'reviewer_ids' => [$treasurer->id]])->assertSessionHasErrors('reviewer_ids');
        $this->actingAs($treasurer)->post(route('reports.audits.store'), ['year' => 2026, 'reviewer_ids' => [$reviewer->id]])->assertSessionHas('success');
        $audit = \App\Models\AuditReview::firstOrFail();
        $this->actingAs($reviewer)->get(route('reports.index'))->assertInertia(fn ($page) => $page->where('full', true));
        $this->actingAs($reviewer)->patch(route('reports.audits.confirm', $audit), ['status' => 'confirmed', 'conclusion' => 'Registres et relevés concordent avec le solde.'])->assertSessionHas('success');
        $this->assertSame('confirmed', $audit->fresh()->status);
    }

    public function test_a_treasurer_cannot_validate_their_own_contribution(): void
    {
        $this->seed(\Database\Seeders\FundAllocationRuleSeeder::class);
        $treasurer = $this->user('treasurer');
        $other = $this->user('treasurer');
        $payment = ContributionPayment::create(['user_id' => $treasurer->id, 'amount' => 75, 'currency' => 'USD', 'payment_method' => PaymentMethod::BankTransfer, 'status' => ContributionPaymentStatus::Pending, 'submitted_at' => now(), 'created_by' => $treasurer->id]);

        $this->actingAs($treasurer)->patch(route('contributions.validate', $payment))->assertSessionHas('success');
        $this->assertSame(ContributionPaymentStatus::Pending, $payment->fresh()->status);
        $this->actingAs($other)->patch(route('contributions.validate', $payment))->assertSessionHas('success');
        $this->assertSame(ContributionPaymentStatus::Validated, $payment->fresh()->status);
    }

    public function test_the_rules_page_lists_treasurers_and_known_fund_details(): void
    {
        $treasurer = $this->user('treasurer');
        \App\Models\Zone::where('code', 'europe')->first()->treasurers()->attach($treasurer);
        $this->setRule('governing_state', 'Maryland');
        $member = $this->user('member');

        $this->actingAs($member)->get(route('policies.show'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('sections.1.blocks.7.2.0.1', $treasurer->name)
            ->where('sections.1.blocks.7.2.0.2', 'Europe')
            ->where('sections.0.blocks.3.1', fn ($text) => str_contains($text, 'Maryland')));
    }

    public function test_notifications_can_be_read_in_the_app(): void
    {
        Notification::swap(new \Illuminate\Notifications\ChannelManager(app()));
        $member = $this->user('member');
        $member->notify(new \App\Notifications\FundNotification(['Test :x', ['x' => 'A']], [['Ligne']], route('reports.index'), mail: false));

        $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('auth.unread_notifications', 1));
        $id = $member->notifications()->first()->id;
        $this->actingAs($member)->get(route('notifications.open', $id))->assertRedirect(route('reports.index'));
        $this->assertSame(0, $member->unreadNotifications()->count());
    }
}
