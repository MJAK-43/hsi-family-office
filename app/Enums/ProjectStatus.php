<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case NeedsInformation = 'needs_information';
    case ApprovedByCommittee = 'approved_by_committee';
    case RejectedByCommittee = 'rejected_by_committee';
    case SubmittedToVote = 'submitted_to_vote';
    case ApprovedByFamily = 'approved_by_family';
    case RejectedByFamily = 'rejected_by_family';
    case Funded = 'funded';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Submitted => 'Soumis',
            self::UnderReview => 'En analyse',
            self::NeedsInformation => 'Compléments demandés',
            self::ApprovedByCommittee => 'Approuvé comité',
            self::RejectedByCommittee => 'Rejeté comité',
            self::SubmittedToVote => 'Soumis au vote',
            self::ApprovedByFamily => 'Approuvé famille',
            self::RejectedByFamily => 'Rejeté famille',
            self::Funded => 'Financé',
            self::InProgress => 'En cours',
            self::Completed => 'Terminé',
            self::Failed => 'Échoué',
            self::Cancelled => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'info',
            self::UnderReview => 'warning',
            self::NeedsInformation => 'warning',
            self::ApprovedByCommittee => 'success',
            self::RejectedByCommittee => 'danger',
            self::SubmittedToVote => 'info',
            self::ApprovedByFamily => 'success',
            self::RejectedByFamily => 'danger',
            self::Funded, self::InProgress => 'success',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}
