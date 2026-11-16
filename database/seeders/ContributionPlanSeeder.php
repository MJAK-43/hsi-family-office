<?php

namespace Database\Seeders;

use App\Enums\ContributionFrequency;
use App\Models\ContributionPlan;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContributionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@hsi-family-office.test')->first();
        $france = Country::where('code', 'FR')->first();
        $cameroun = Country::where('code', 'CM')->first();
        $canada = Country::where('code', 'CA')->first();
        $usa = Country::where('code', 'US')->first();

        $plans = [
            [
                'name' => 'Cotisation Europe',
                'description' => 'Cotisation mensuelle standard pour les membres basés en Europe.',
                'amount' => 20,
                'currency' => 'USD',
                'frequency' => ContributionFrequency::Monthly,
                'country_id' => $france?->id,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Cotisation Afrique',
                'description' => 'Cotisation mensuelle adaptée pour les membres basés en Afrique.',
                'amount' => 10,
                'currency' => 'USD',
                'frequency' => ContributionFrequency::Monthly,
                'country_id' => $cameroun?->id,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Cotisation Amérique du Nord',
                'description' => 'Cotisation mensuelle pour les membres en Amérique du Nord.',
                'amount' => 25,
                'currency' => 'USD',
                'frequency' => ContributionFrequency::Monthly,
                'country_id' => $canada?->id ?? $usa?->id,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Contribution libre',
                'description' => 'Montant libre pour un don ponctuel.',
                'amount' => 0,
                'currency' => 'USD',
                'frequency' => ContributionFrequency::OneTime,
                'country_id' => null,
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            ContributionPlan::updateOrCreate(
                ['name' => $plan['name']],
                [...$plan, 'created_by' => $admin?->id],
            );
        }
    }
}
