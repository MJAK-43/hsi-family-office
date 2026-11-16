<?php

namespace App\Enums;

enum ContributionDueStatus: string
{
    case Pending = 'pending';
    case Late = 'late';
    case Paid = 'paid';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À payer',
            self::Late => 'En retard',
            self::Paid => 'Payée',
            self::Waived => 'Exonérée',
        };
    }
}
