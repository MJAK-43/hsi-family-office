<?php

namespace App\Enums;

enum ProjectSector: string
{
    case RealEstate = 'real_estate';
    case Agriculture = 'agriculture';
    case FishFarming = 'fish_farming';
    case Technology = 'technology';
    case Education = 'education';
    case Health = 'health';
    case Energy = 'energy';
    case Commerce = 'commerce';
    case Finance = 'finance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RealEstate => 'Immobilier',
            self::Agriculture => 'Agriculture',
            self::FishFarming => 'Pisciculture',
            self::Technology => 'Technologie',
            self::Education => 'Éducation',
            self::Health => 'Santé',
            self::Energy => 'Énergie',
            self::Commerce => 'Commerce',
            self::Finance => 'Finance',
            self::Other => 'Autre',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RealEstate => 'building',
            self::Agriculture => 'wheat',
            self::FishFarming => 'fish',
            self::Technology => 'cpu',
            self::Education => 'graduation-cap',
            self::Health => 'heart-pulse',
            self::Energy => 'zap',
            self::Commerce => 'store',
            self::Finance => 'landmark',
            self::Other => 'folder',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'icon' => $case->icon(),
        ], self::cases());
    }
}
