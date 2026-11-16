<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectCriterion;
use App\Models\User;
use App\Notifications\FundNotification;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Instruction d'un dossier par le comité d'investissement :
 * liste de critères copiée au dépôt, vérifications automatiques, décision motivée.
 */
class ProjectReviewService
{
    public const DECISIONS = [
        'accept' => 'Accepter',
        'refuse' => 'Refuser',
        'information' => 'Demander des compléments',
    ];

    public function __construct(private EligibilityService $eligibility) {}

    /** Copie la liste de critères du type dans le dossier (une seule fois) puis relance les vérifications automatiques. */
    public function prepareCriteria(Project $project): void
    {
        $project->loadMissing('type.criteria');
        if (! $project->criteria()->exists() && $project->type) {
            foreach ($project->type->criteria->where('is_active', true) as $criterion) {
                $project->criteria()->create([
                    'project_type_criterion_id' => $criterion->id,
                    'label' => $criterion->label,
                    'description' => $criterion->description,
                    'is_blocking' => $criterion->is_blocking,
                    'auto_check' => $criterion->auto_check,
                    'position' => $criterion->position,
                    'status' => 'pending',
                ]);
            }
        }
        $this->refreshAutoChecks($project);
    }

    /** Les critères automatiques sont recalculés ; une décision manuelle du comité n'est jamais écrasée. */
    public function refreshAutoChecks(Project $project): void
    {
        $checks = $this->eligibility->checks($project->fresh(['type', 'owner']));
        foreach ($project->criteria()->whereNotNull('auto_check')->get() as $criterion) {
            $check = $checks[$criterion->auto_check] ?? null;
            if (! $check) continue;
            $attributes = ['auto_result' => $check['result'], 'auto_detail' => mb_substr($check['detail'], 0, 255)];
            if ($criterion->checked_by === null) {
                $attributes['status'] = match ($check['result']) {
                    'pass' => 'validated',
                    'fail' => 'not_validated',
                    default => 'not_applicable',
                };
            }
            $criterion->update($attributes);
        }
    }

    public function checkCriterion(ProjectCriterion $criterion, User $user, string $status, ?string $comment): ProjectCriterion
    {
        $project = $criterion->project;
        $this->assertReviewable($project, $user);
        $before = $criterion->status;
        $criterion->update(['status' => $status, 'comment' => $comment, 'checked_by' => $user->id, 'checked_at' => now()]);
        if ($project->status === ProjectStatus::Submitted) {
            $project->update(['status' => ProjectStatus::UnderReview]);
        }
        activity('projects')->performedOn($project)->causedBy($user)->event('project.criterion.checked')
            ->withProperties(['criterion' => $criterion->label, 'before' => $before, 'after' => $status, 'comment' => $comment])
            ->log('Critère vérifié : '.$criterion->label.' — '.ProjectCriterion::STATUSES[$status]);

        return $criterion->fresh();
    }

    public function decide(Project $project, User $user, string $decision, string $reason): Project
    {
        $this->assertReviewable($project, $user);

        if ($decision === 'accept') {
            $blocking = $project->criteria()->where('is_blocking', true)->whereNotIn('status', ['validated', 'not_applicable'])->pluck('label');
            if ($blocking->isNotEmpty()) {
                throw new RuntimeException('Impossible d’accepter : critère(s) bloquant(s) non validé(s) — '.$blocking->implode(', ').'.');
            }
        }

        return DB::transaction(function () use ($project, $user, $decision, $reason) {
            $attributes = [
                'committee_decision' => $decision, 'decided_at' => now(),
                'reviewed_at' => now(), 'reviewed_by' => $user->id,
            ];
            [$status, $event, $message] = match ($decision) {
                'accept' => [ProjectStatus::ApprovedByCommittee, 'project.approved_by_committee', 'Projet approuvé par le comité investissement'],
                'refuse' => [ProjectStatus::RejectedByCommittee, 'project.rejected_by_committee', 'Projet rejeté par le comité investissement'],
                'information' => [ProjectStatus::NeedsInformation, 'project.information_requested', 'Compléments demandés au porteur'],
            };
            $attributes['status'] = $status;
            $attributes['committee_decision_comment'] = $decision === 'accept' ? $reason : null;
            $attributes['rejected_reason'] = $decision === 'refuse' ? $reason : null;
            $attributes['information_request'] = $decision === 'information' ? $reason : null;
            $project->update($attributes);

            activity('projects')->performedOn($project)->causedBy($user)->event($event)
                ->withProperties(['decision' => $decision, 'reason' => $reason])->log($message);

            $subject = match ($decision) {
                'accept' => ['Votre demande « :title » est acceptée par le comité', ['title' => $project->title]],
                'refuse' => ['Votre demande « :title » est refusée', ['title' => $project->title]],
                'information' => ['Des compléments sont demandés pour « :title »', ['title' => $project->title]],
            };
            $lines = [['Décision du comité d’investissement : :decision.', ['decision' => __(self::DECISIONS[$decision])]], ['Motif : :reason', ['reason' => $reason]]];
            $lines[] = match ($decision) {
                'accept' => ['La demande va maintenant être soumise au vote des membres.'],
                'refuse' => ['Le détail de chaque critère est visible dans votre dossier.'],
                'information' => ['Complétez votre dossier puis déposez-le de nouveau.'],
            };
            Notifier::send([$project->owner], new FundNotification($subject, $lines, route('projects.show', $project), 'Voir mon dossier', 'project'));

            return $project->fresh();
        });
    }

    private function assertReviewable(Project $project, User $user): void
    {
        if ($project->owner_id === $user->id) {
            throw new RuntimeException('Vous ne pouvez pas instruire votre propre demande.');
        }
        if (! in_array($project->status, [ProjectStatus::Submitted, ProjectStatus::UnderReview], true)) {
            throw new RuntimeException('Ce dossier n’est pas en cours d’instruction.');
        }
    }
}
