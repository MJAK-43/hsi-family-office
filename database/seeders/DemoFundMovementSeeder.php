<?php

namespace Database\Seeders;

use App\Enums\ContributionPaymentStatus;
use App\Models\ContributionPayment;
use App\Services\FundService;
use Illuminate\Database\Seeder;

class DemoFundMovementSeeder extends Seeder
{
    public function run(): void
    {
        $fundService = app(FundService::class);

        ContributionPayment::query()
            ->where('status', ContributionPaymentStatus::Validated)
            ->each(function (ContributionPayment $payment) use ($fundService) {
                try {
                    $fundService->allocateContributionPayment($payment);
                } catch (\Throwable) {
                    // Ignore si règle absente lors du seed partiel
                }
            });

        $fundService->recalculateAllBalances();
    }
}
