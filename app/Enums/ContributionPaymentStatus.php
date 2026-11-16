<?php

namespace App\Enums;

enum ContributionPaymentStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Late = 'late';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Validated => 'Validée',
            self::Rejected => 'Refusée',
            self::Late => 'En retard',
            self::Cancelled => 'Annulée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Validated => 'success',
            self::Rejected => 'danger',
            self::Late => 'danger',
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
