<?php

namespace App\Enums;

enum VoteType: string
{
    case ProjectFunding = 'project_funding';
    case Budget = 'budget';
    case Governance = 'governance';
    case Investment = 'investment';
    case Membership = 'membership';
    case Constitution = 'constitution';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ProjectFunding => 'Financement projet',
            self::Budget => 'Budget',
            self::Governance => 'Gouvernance',
            self::Investment => 'Investissement',
            self::Membership => 'Adhésion',
            self::Constitution => 'Constitution',
            self::Other => 'Autre',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
