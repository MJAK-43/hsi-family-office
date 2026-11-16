<?php

namespace App\Enums;

enum VoteResult: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NoQuorum = 'no_quorum';
    case Tied = 'tied';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approuvé',
            self::Rejected => 'Rejeté',
            self::NoQuorum => 'Quorum non atteint',
            self::Tied => 'Égalité',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::NoQuorum => 'warning',
            self::Tied => 'neutral',
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
