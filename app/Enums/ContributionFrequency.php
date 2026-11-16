<?php

namespace App\Enums;

enum ContributionFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    case OneTime = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensuelle',
            self::Quarterly => 'Trimestrielle',
            self::Yearly => 'Annuelle',
            self::OneTime => 'Ponctuelle',
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
