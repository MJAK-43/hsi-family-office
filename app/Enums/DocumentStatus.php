<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon', self::InReview => 'À valider',
            self::Published => 'Publié', self::Archived => 'Archivé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral', self::InReview => 'warning',
            self::Published => 'success', self::Archived => 'info',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
