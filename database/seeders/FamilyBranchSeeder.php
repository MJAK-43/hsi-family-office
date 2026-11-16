<?php

namespace Database\Seeders;

use App\Models\FamilyBranch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FamilyBranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['name' => 'Finance', 'icon' => 'landmark', 'description' => 'Gestion patrimoniale et investissements financiers'],
            ['name' => 'Immobilier', 'icon' => 'building', 'description' => 'Investissements et gestion immobilière'],
            ['name' => 'Industrie', 'icon' => 'factory', 'description' => 'Entreprises industrielles et manufacturières'],
            ['name' => 'Santé', 'icon' => 'heart-pulse', 'description' => 'Secteur médical et bien-être'],
            ['name' => 'Technologie', 'icon' => 'cpu', 'description' => 'Innovation et entreprises technologiques'],
            ['name' => 'Éducation', 'icon' => 'graduation-cap', 'description' => 'Formation et transmission du savoir'],
            ['name' => 'Commerce', 'icon' => 'store', 'description' => 'Commerce et distribution'],
            ['name' => 'Art & Culture', 'icon' => 'palette', 'description' => 'Patrimoine culturel et arts'],
        ];

        foreach ($branches as $branch) {
            FamilyBranch::updateOrCreate(
                ['slug' => Str::slug($branch['name'])],
                [...$branch, 'is_active' => true],
            );
        }
    }
}
