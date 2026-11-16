<?php

namespace Database\Seeders;

use App\Models\FundAccount;
use App\Models\FundAllocationRule;
use App\Models\FundAllocationRuleItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class FundAllocationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@hsi-family-office.test')->first();

        $rule = FundAllocationRule::updateOrCreate(
            ['name' => 'Répartition standard HSI'],
            [
                'description' => 'Allocation par défaut : 40% investissement, 30% projets, 20% solidarité, 10% éducation.',
                'is_default' => true,
                'is_active' => true,
                'created_by' => $admin?->id,
            ],
        );

        $items = [
            'long-term-investment' => 40,
            'family-projects' => 30,
            'solidarity' => 20,
            'education' => 10,
        ];

        foreach ($items as $slug => $percentage) {
            $account = FundAccount::where('slug', $slug)->first();
            if (! $account) {
                continue;
            }

            FundAllocationRuleItem::updateOrCreate(
                [
                    'fund_allocation_rule_id' => $rule->id,
                    'fund_account_id' => $account->id,
                ],
                ['percentage' => $percentage],
            );
        }
    }
}
