<?php

namespace App\Enums;

enum MajorityRule: string
{
    case Simple = 'simple';
    case Absolute = 'absolute';
    case Supermajority = 'supermajority';
    case Threshold = 'threshold';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Majorité simple (≥ 50 % des exprimés)',
            self::Absolute => 'Majorité absolue (≥ 50 % des électeurs)',
            self::Supermajority => 'Majorité qualifiée (≥ 66 % des exprimés)',
            self::Threshold => 'Règlement intérieur (≥ 80 % des voix pour et contre)',
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
