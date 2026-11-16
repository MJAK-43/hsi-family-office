<?php

namespace App\Support;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

class ProjectAccess
{
    public static function canViewAll(User $user): bool
    {
        return $user->can('view all projects');
    }

    public static function canView(User $user): bool
    {
        return $user->can('view projects') || self::canViewAll($user);
    }

    public static function isPublicStatus(ProjectStatus $status): bool
    {
        return in_array($status, [
            ProjectStatus::ApprovedByCommittee,
            ProjectStatus::SubmittedToVote,
            ProjectStatus::ApprovedByFamily,
            ProjectStatus::Funded,
            ProjectStatus::InProgress,
            ProjectStatus::Completed,
        ], true);
    }

    public static function canViewProject(User $user, Project $project): bool
    {
        if (self::canViewAll($user)) {
            return true;
        }

        if ($project->owner_id === $user->id && self::canView($user)) {
            return true;
        }

        return self::canView($user) && self::isPublicStatus($project->status);
    }

    public static function canModify(User $user, Project $project): bool
    {
        return $project->isEditableByOwner() && (
            $user->can('edit all projects')
            || ($project->owner_id === $user->id && $user->can('edit own projects'))
        );
    }

    public static function canSubmit(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id
            && $user->can('submit projects')
            && $project->isEditableByOwner();
    }

    public static function canEvaluate(User $user): bool
    {
        return $user->can('review projects');
    }

    public static function canApprove(User $user): bool
    {
        return $user->can('approve projects');
    }

    public static function canReject(User $user): bool
    {
        return $user->can('reject projects');
    }

    public static function canSubmitToVote(User $user): bool
    {
        return $user->can('submit projects to vote');
    }

    public static function canFund(User $user, Project $project): bool
    {
        return $user->can('fund projects')
            && $project->owner_id !== $user->id
            && $project->status === ProjectStatus::ApprovedByFamily;
    }

    /** Instruire un dossier (cocher les critères, décider) : comité ou président, jamais le demandeur. */
    public static function canReview(User $user, Project $project): bool
    {
        return ($user->can('review projects') || $user->can('approve projects'))
            && $project->owner_id !== $user->id
            && in_array($project->status, [ProjectStatus::Submitted, ProjectStatus::UnderReview], true);
    }

    /** Voir les éléments internes (pièces du comité, validations, historique complet). */
    public static function canSeeInternal(User $user, Project $project): bool
    {
        return $project->owner_id !== $user->id && self::canViewAll($user);
    }

    public static function canRecordRepayment(User $user, Project $project): bool
    {
        return $user->can('fund projects')
            && $project->owner_id !== $user->id
            && $project->status === ProjectStatus::InProgress
            && $project->installments()->exists();
    }

    public static function canDelete(User $user): bool
    {
        return $user->can('delete projects');
    }

    public static function canManageExecution(User $user, Project $project): bool
    {
        return self::canViewProject($user, $project)
            && ($user->can('fund projects') || $user->can('approve projects'));
    }

    public static function canReportProgress(User $user, Project $project): bool
    {
        return self::canManageExecution($user, $project)
            || ($project->owner_id === $user->id && $user->can('edit own projects'));
    }

    public static function canManageDocuments(User $user, Project $project): bool
    {
        if ($user->can('manage project documents')) {
            return true;
        }

        return $project->owner_id === $user->id && $project->isEditableByOwner();
    }

    public static function canDownloadDocument(User $user, Project $project, ?\App\Models\ProjectDocument $document = null): bool
    {
        if ($document && $document->visibility === 'committee' && ! self::canSeeInternal($user, $project)) {
            return false;
        }

        if ($project->owner_id === $user->id) {
            return true;
        }

        if ($user->hasRole('investment_committee')) {
            return true;
        }

        return $user->hasAnyRole(['president', 'secretary', 'treasurer', 'super_admin']);
    }

    public static function canUploadDocument(User $user, Project $project): bool
    {
        if (self::canManageDocuments($user, $project)) {
            return true;
        }

        return $project->owner_id === $user->id && $project->isEditableByOwner();
    }

    /** Le comité peut joindre des pièces internes ou visibles par le demandeur. */
    public static function canAttachCommitteeDocument(User $user, Project $project): bool
    {
        return $project->owner_id !== $user->id
            && ($user->can('manage project documents') || $user->can('review projects') || $user->can('approve projects') || $user->can('fund projects'));
    }
}
