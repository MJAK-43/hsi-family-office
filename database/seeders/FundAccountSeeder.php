<?php

namespace Database\Seeders;

use App\Enums\FundAccountType;
use App\Models\FundAccount;
use App\Models\User;
use Illuminate\Database\Seeder;

class FundAccountSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@hsi-family-office.test')->first();

        $accounts = [
            [
                'name' => 'Investissement long terme',
                'slug' => 'long-term-investment',
                'type' => FundAccountType::LongTermInvestment,
                'description' => 'Poche dédiée aux investissements patrimoniaux à long terme.',
            ],
            [
                'name' => 'Projets familiaux',
                'slug' => 'family-projects',
                'type' => FundAccountType::FamilyProjects,
                'description' => 'Financement des projets portés par les membres de la famille.',
            ],
            [
                'name' => 'Solidarité',
                'slug' => 'solidarity',
                'type' => FundAccountType::Solidarity,
                'description' => 'Fonds de solidarité pour les membres en difficulté.',
            ],
            [
                'name' => 'Éducation',
                'slug' => 'education',
                'type' => FundAccountType::Education,
                'description' => 'Bourses, formations et programmes éducatifs HSI.',
            ],
            [
                'name' => 'Trésorerie',
                'slug' => 'treasury',
                'type' => FundAccountType::Treasury,
                'description' => 'Poche de trésorerie pour ajustements et mouvements manuels.',
            ],
        ];

        foreach ($accounts as $account) {
            FundAccount::updateOrCreate(
                ['slug' => $account['slug']],
                [
                    ...$account,
                    'currency' => 'USD',
                    'current_balance' => 0,
                    'is_active' => true,
                    'created_by' => $admin?->id,
                ],
            );
        }
    }
}
