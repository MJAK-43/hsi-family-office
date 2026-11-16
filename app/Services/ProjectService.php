<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectEvaluation;
use App\Models\ProjectMilestone;
use App\Models\ProjectProgressReport;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProjectService
{
    public function __construct(private FinancialOperationService $operations) {}

    public function submit(Project $project): Project
    {
        if (! $project->isEditableByOwner()) {
            throw new RuntimeException('Ce projet ne peut pas être soumis dans son état actuel.');
        }

        $project->loadMissing('type');
        if ($project->type && ! $project->type->is_active) {
            throw new RuntimeException('Ce type de projet n’est plus proposé : choisissez un autre type.');
        }

        if (! $project->respectsFiftyFiftyRule()) {
            throw new RuntimeException($project->type && ! $project->type->requires_personal_contribution
                ? 'Le montant demandé doit être positif.'
                : 'Le projet ne respecte pas la règle 50/50.');
        }

        if ($project->isLoan() && (! $project->loan_duration_months || ! $project->repayment_plan || ! $project->presentation_date)) {
            throw new RuntimeException('Pour un prêt, indiquez la durée de remboursement, le plan de remboursement et la date de présentation au groupe.');
        }

        $blockers = app(EligibilityService::class)->submissionBlockers($project);
        if ($blockers) {
            throw new RuntimeException('Dépôt impossible : '.implode(' · ', $blockers));
        }

        if (empty($project->title)
            || Money::fromDecimal($project->total_budget, $project->currency)->compare(Money::zero($project->currency)) <= 0) {
            throw new RuntimeException('Le projet doit avoir un titre et un budget valide.');
        }

        return DB::transaction(function () use ($project) {
            $resubmitted = $project->status === ProjectStatus::NeedsInformation;
            $project->update([
                'status' => ProjectStatus::Submitted,
                'submitted_at' => $project->submitted_at && $resubmitted ? $project->submitted_at : now(),
                'rejected_reason' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'committee_decision_comment' => null,
                'committee_decision' => null,
            ]);

            app(ProjectReviewService::class)->prepareCriteria($project);

            activity('projects')
                ->performedOn($project)
                ->causedBy($project->owner)
                ->event($resubmitted ? 'project.resubmitted' : 'project.submitted')
                ->withProperties(['status' => ProjectStatus::Submitted->value])
                ->log($resubmitted ? 'Dossier complété et redéposé' : 'Projet soumis pour analyse');

            \App\Support\Notifier::send(\App\Support\Notifier::committee()->reject(fn ($u) => $u->id === $project->owner_id), new \App\Notifications\FundNotification(
                [$resubmitted ? 'Dossier complété : :title' : 'Nouvelle demande à instruire : :title', ['title' => $project->title]],
                [[':name a déposé une demande « :type » de :amount.', ['name' => $project->owner?->name, 'type' => __($project->type?->name ?? 'Projet'), 'amount' => \App\Support\ProjectFormatter::money($project->requested_amount)]]],
                route('projects.show', $project), 'Instruire la demande', 'project',
            ));

            return $project->fresh();
        });
    }

    public function evaluate(Project $project, User $evaluator, array $scores): ProjectEvaluation
    {
        if (! in_array($project->status, [ProjectStatus::Submitted, ProjectStatus::UnderReview], true)) {
            throw new RuntimeException('Ce projet ne peut pas être évalué dans son état actuel.');
        }

        $this->validateScores($scores);

        return DB::transaction(function () use ($project, $evaluator, $scores) {
            if ($project->status === ProjectStatus::Submitted) {
                $project->update(['status' => ProjectStatus::UnderReview]);
            }

            $totalScore = ProjectEvaluation::calculateTotalScore($scores);

            $evaluation = ProjectEvaluation::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'evaluator_id' => $evaluator->id,
                ],
                [
                    ...$scores,
                    'total_score' => $totalScore,
                    'recommendation' => $scores['recommendation'] ?? null,
                    'comment' => $scores['comment'] ?? null,
                    'evaluated_at' => now(),
                ],
            );

            activity('projects')
                ->performedOn($project)
                ->causedBy($evaluator)
                ->event('project.evaluated')
                ->withProperties([
                    'evaluator_id' => $evaluator->id,
                    'total_score' => $totalScore,
                ])
                ->log('Projet évalué par le comité investissement');

            return $evaluation;
        });
    }

    public function approveByCommittee(Project $project, User $reviewer, ?string $comment = null): Project
    {
        return app(ProjectReviewService::class)->decide($project, $reviewer, 'accept', $comment ?: 'Dossier conforme aux critères.');
    }

    public function rejectByCommittee(Project $project, User $reviewer, string $reason): Project
    {
        return app(ProjectReviewService::class)->decide($project, $reviewer, 'refuse', $reason);
    }

    /** Lance le vote des membres tout de suite, ou le programme (dates modifiables ensuite). */
    public function submitToVote(Project $project, User $user, ?string $opensAt = null, ?string $closesAt = null): Project
    {
        $relaunch = $project->status === ProjectStatus::SubmittedToVote && ! $project->activeVote()->exists();
        if ($project->status !== ProjectStatus::ApprovedByCommittee && ! $relaunch) {
            throw new RuntimeException('Seuls les projets approuvés par le comité peuvent être soumis au vote.');
        }

        return DB::transaction(function () use ($project, $user, $opensAt, $closesAt) {
            $project->update([
                'status' => ProjectStatus::SubmittedToVote,
                'submitted_to_vote_at' => now(),
            ]);

            $vote = app(VoteService::class)->openForProject($project->fresh(), $user, null, $opensAt, $closesAt);

            activity('projects')
                ->performedOn($project)
                ->causedBy($user)
                ->event('project.submitted_to_vote')
                ->withProperties(['vote_id' => $vote->id, 'opens_at' => $vote->opens_at?->toDateTimeString(), 'closes_at' => $vote->closes_at?->toDateTimeString()])
                ->log($vote->status === \App\Enums\VoteStatus::Scheduled ? 'Vote des membres programmé' : 'Projet soumis au vote familial');

            return $project->fresh();
        });
    }

    public function fund(Project $project, User $user, ?string $idempotencyKey = null, mixed $amountToFund = null): Project
    {
        return DB::transaction(function () use ($project, $user, $idempotencyKey, $amountToFund) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $idempotencyKey ??= "project-funding:{$project->id}";
            $claim = $this->operations->claim('project_funding', $idempotencyKey, [
                'project_id' => $project->id,
                'amount' => $amountToFund ?? $project->requested_amount,
                'currency' => $project->currency,
            ], $user);

            if ($claim->replayed) {
                return $project;
            }

            if ($project->status !== ProjectStatus::ApprovedByFamily) {
                throw new RuntimeException('Seul un projet approuvé par la famille peut être financé.');
            }

            if ($project->funded_at !== null) {
                throw new RuntimeException('Ce projet est déjà financé.');
            }

            $movement = app(FundService::class)->createProjectFundingMovement(
                $project,
                $user,
                $claim->operation->id,
                $amountToFund,
            );
            $amount = Money::fromDecimal($amountToFund ?? $project->requested_amount, $project->currency);

            $project->update([
                'status' => ProjectStatus::Funded,
                'funded_at' => now(),
                'funded_by' => $user->id,
                'funded_amount' => $amount->decimal(),
                'updated_by' => $user->id,
            ]);

            activity('projects')
                ->performedOn($project)
                ->causedBy($user)
                ->event('project.funded')
                ->withProperties([
                    'funded_amount' => $amount->decimal(),
                    'fund_movement_id' => $movement->id,
                    'fund_account_id' => $movement->fund_account_id,
                ])
                ->log('Projet financé depuis la poche Projets familiaux');

            $this->operations->complete($claim->operation, [
                'project_id' => $project->id,
                'movement_id' => $movement->id,
            ]);

            return $project->fresh();
        });
    }

    public function start(Project $project, User $user): Project
    {
        return $this->transition($project, $user, ProjectStatus::Funded, ProjectStatus::InProgress, [
            'started_at' => now(),
            'progress_percent' => max(1, (int) $project->progress_percent),
        ], 'project.started', 'Exécution du projet démarrée');
    }

    public function complete(Project $project, User $user, string $note): Project
    {
        return $this->transition($project, $user, ProjectStatus::InProgress, ProjectStatus::Completed, [
            'completed_at' => now(),
            'progress_percent' => 100,
            'closure_note' => $note,
        ], 'project.completed', 'Projet clôturé avec succès');
    }

    public function fail(Project $project, User $user, string $reason): Project
    {
        return $this->transition($project, $user, ProjectStatus::InProgress, ProjectStatus::Failed, [
            'failed_at' => now(),
            'closure_note' => $reason,
        ], 'project.failed', 'Projet déclaré en échec');
    }

    public function cancel(Project $project, User $user, string $reason): Project
    {
        if (! in_array($project->status, [ProjectStatus::Funded, ProjectStatus::InProgress], true)) {
            throw new RuntimeException('Seul un projet financé ou en cours peut être annulé.');
        }

        return $this->transition($project, $user, $project->status, ProjectStatus::Cancelled, [
            'cancelled_at' => now(),
            'closure_note' => $reason,
        ], 'project.cancelled', 'Projet annulé');
    }

    public function addMilestone(Project $project, User $user, array $data): ProjectMilestone
    {
        if (! in_array($project->status, [ProjectStatus::Funded, ProjectStatus::InProgress], true)) {
            throw new RuntimeException('Les jalons sont disponibles après le financement du projet.');
        }

        return DB::transaction(function () use ($project, $user, $data) {
            $milestone = $project->milestones()->create([...$data, 'created_by' => $user->id]);
            activity('projects')->performedOn($project)->causedBy($user)
                ->event('project.milestone.created')->withProperties(['milestone_id' => $milestone->id])
                ->log('Jalon de projet ajouté');

            return $milestone;
        });
    }

    public function updateMilestone(ProjectMilestone $milestone, User $user, array $data): ProjectMilestone
    {
        return DB::transaction(function () use ($milestone, $user, $data) {
            $data['completed_at'] = ($data['status'] ?? null) === 'completed' ? ($milestone->completed_at ?? now()) : null;
            $milestone->update($data);
            activity('projects')->performedOn($milestone->project)->causedBy($user)
                ->event('project.milestone.updated')->withProperties(['milestone_id' => $milestone->id, 'status' => $milestone->status->value])
                ->log('Jalon de projet mis à jour');

            return $milestone->fresh();
        });
    }

    public function addProgressReport(Project $project, User $user, array $data): ProjectProgressReport
    {
        if ($project->status !== ProjectStatus::InProgress) {
            throw new RuntimeException('Un rapport ne peut être ajouté que pour un projet en cours.');
        }

        return DB::transaction(function () use ($project, $user, $data) {
            $report = $project->progressReports()->create([...$data, 'reported_by' => $user->id]);
            $project->update(['progress_percent' => $data['progress_percent'], 'updated_by' => $user->id]);
            activity('projects')->performedOn($project)->causedBy($user)
                ->event('project.progress_report.created')->withProperties(['report_id' => $report->id, 'progress_percent' => $data['progress_percent']])
                ->log('Rapport d’avancement ajouté');

            return $report;
        });
    }

    private function transition(Project $project, User $user, ProjectStatus $from, ProjectStatus $to, array $attributes, string $event, string $message): Project
    {
        return DB::transaction(function () use ($project, $user, $from, $to, $attributes, $event, $message) {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);
            if ($locked->status !== $from) {
                throw new RuntimeException("Transition impossible depuis le statut {$locked->status->label()}.");
            }
            $locked->update([...$attributes, 'status' => $to, 'updated_by' => $user->id]);
            activity('projects')->performedOn($locked)->causedBy($user)->event($event)
                ->withProperties(['from' => $from->value, 'to' => $to->value])->log($message);

            return $locked->fresh();
        });
    }

    public static function validateBudgetRule(
        mixed $totalBudget,
        mixed $personalContribution,
        mixed $requestedAmount,
        string $currency,
    ): void {
        $total = Money::fromDecimal($totalBudget, $currency);
        $personal = Money::fromDecimal($personalContribution, $currency);
        $requested = Money::fromDecimal($requestedAmount, $currency);

        if ($total->compare(Money::fromDecimal('1.00', $currency)) < 0) {
            throw new InvalidArgumentException('Le budget total doit être au moins de 1.');
        }

        if ($personal->add($personal)->compare($total) < 0) {
            throw new InvalidArgumentException('L\'apport personnel doit représenter au minimum 50 % du budget total.');
        }

        if ($requested->add($requested)->compare($total) > 0) {
            throw new InvalidArgumentException('Le montant demandé à HSI ne peut pas dépasser 50 % du budget total.');
        }

        if ($personal->add($requested)->compare($total) > 0) {
            throw new InvalidArgumentException('L\'apport personnel et le montant demandé ne peuvent pas dépasser le budget total.');
        }
    }

    private function validateScores(array $scores): void
    {
        $criteria = [
            'profitability_score',
            'risk_score',
            'founder_score',
            'family_impact_score',
            'sustainability_score',
            'values_alignment_score',
        ];

        foreach ($criteria as $criterion) {
            if (! isset($scores[$criterion]) || $scores[$criterion] === null) {
                continue;
            }

            $value = (int) $scores[$criterion];
            if ($value < 0 || $value > ProjectEvaluation::MAX_SCORE_PER_CRITERION) {
                throw new InvalidArgumentException(__('Le score :criterion doit être entre 0 et 20.', ['criterion' => $criterion]));
            }
        }
    }
}
