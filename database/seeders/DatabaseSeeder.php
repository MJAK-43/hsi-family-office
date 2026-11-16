<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CountrySeeder::class,
            FamilyBranchSeeder::class,
            ContributionPlanSeeder::class,
            FundAccountSeeder::class,
            FundAllocationRuleSeeder::class,
            DocumentAdministrationSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
