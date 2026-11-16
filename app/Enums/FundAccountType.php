<?php

namespace App\Enums;

enum FundAccountType: string
{
    case LongTermInvestment = 'long_term_investment';
    case FamilyProjects = 'family_projects';
    case Solidarity = 'solidarity';
    case Education = 'education';
    case Treasury = 'treasury';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LongTermInvestment => 'Investissement long terme',
            self::FamilyProjects => 'Projets familiaux',
            self::Solidarity => 'Solidarité',
            self::Education => 'Éducation',
            self::Treasury => 'Trésorerie',
            self::Other => 'Autre compte',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LongTermInvestment => 'gold',
            self::FamilyProjects => 'info',
            self::Solidarity => 'success',
            self::Education => 'warning',
            self::Treasury => 'neutral',
            self::Other => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LongTermInvestment => 'TrendingUp',
            self::FamilyProjects => 'FolderKanban',
            self::Solidarity => 'HeartHandshake',
            self::Education => 'GraduationCap',
            self::Treasury => 'Landmark',
            self::Other => 'Wallet',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
            'icon' => $case->icon(),
        ], self::cases());
    }
}
