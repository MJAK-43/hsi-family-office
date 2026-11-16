<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Les donnees de demonstration sont interdites hors des environnements local et testing.');
        }

        $this->call([
            DemoMemberSeeder::class,
            DemoContributionPaymentSeeder::class,
            DemoFundMovementSeeder::class,
            DemoProjectSeeder::class,
            DemoVoteSeeder::class,
        ]);
    }
}
