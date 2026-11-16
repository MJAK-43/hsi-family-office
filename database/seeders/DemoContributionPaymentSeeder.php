<?php

namespace Database\Seeders;

use App\Enums\ContributionPaymentStatus;
use App\Enums\PaymentMethod;
use App\Models\ContributionPayment;
use App\Models\ContributionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoContributionPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@hsi-family-office.test')->first();
        $treasurer = User::where('email', 'isabelle.m@hsi-family-office.test')->first();
        $europePlan = ContributionPlan::where('name', 'Cotisation Europe')->first();
        $afriquePlan = ContributionPlan::where('name', 'Cotisation Afrique')->first();

        $members = User::whereIn('email', [
            'admin@hsi-family-office.test',
            'claire.d@hsi-family-office.test',
            'marc.d@hsi-family-office.test',
            'sophie.n@hsi-family-office.test',
            'jules.n@hsi-family-office.test',
            'thomas.l@hsi-family-office.test',
        ])->get()->keyBy('email');

        $payments = [
            [
                'user' => 'admin@hsi-family-office.test',
                'plan' => $europePlan,
                'amount' => 20,
                'month' => 5, 'year' => 2025,
                'method' => PaymentMethod::BankTransfer,
                'reference' => 'TRF-2025-0524',
                'status' => ContributionPaymentStatus::Validated,
                'validated_by' => $treasurer?->id,
            ],
            [
                'user' => 'claire.d@hsi-family-office.test',
                'plan' => $europePlan,
                'amount' => 20,
                'month' => 5, 'year' => 2025,
                'method' => PaymentMethod::Stripe,
                'reference' => 'CB-2025-0514',
                'status' => ContributionPaymentStatus::Validated,
                'validated_by' => $treasurer?->id,
            ],
            [
                'user' => 'marc.d@hsi-family-office.test',
                'plan' => $europePlan,
                'amount' => 20,
                'month' => 5, 'year' => 2025,
                'method' => PaymentMethod::Paypal,
                'reference' => 'PP-2025-0513',
                'status' => ContributionPaymentStatus::Pending,
            ],
            [
                'user' => 'sophie.n@hsi-family-office.test',
                'plan' => $afriquePlan,
                'amount' => 10,
                'month' => 4, 'year' => 2025,
                'method' => PaymentMethod::OrangeMoney,
                'reference' => 'OM-2025-0412',
                'status' => ContributionPaymentStatus::Rejected,
                'rejected_by' => $treasurer?->id,
                'rejection_reason' => 'Preuve de paiement illisible, merci de resoumettre.',
            ],
            [
                'user' => 'jules.n@hsi-family-office.test',
                'plan' => $europePlan,
                'amount' => 20,
                'month' => 3, 'year' => 2025,
                'method' => PaymentMethod::BankTransfer,
                'reference' => 'TRF-2025-0310',
                'status' => ContributionPaymentStatus::Late,
            ],
            [
                'user' => 'thomas.l@hsi-family-office.test',
                'plan' => $europePlan,
                'amount' => 20,
                'month' => 5, 'year' => 2025,
                'method' => PaymentMethod::Cash,
                'reference' => 'ESP-2025-0501',
                'status' => ContributionPaymentStatus::Pending,
            ],
        ];

        foreach ($payments as $data) {
            $user = $members->get($data['user']);
            if (! $user) {
                continue;
            }

            $periodMonths = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            $periodLabel = ($periodMonths[$data['month']] ?? '') . " {$data['year']}";

            $attrs = [
                'user_id' => $user->id,
                'contribution_plan_id' => $data['plan']?->id,
                'amount' => $data['amount'],
                'currency' => 'USD',
                'payment_month' => $data['month'],
                'payment_year' => $data['year'],
                'payment_period_label' => $periodLabel,
                'payment_method' => $data['method'],
                'reference' => $data['reference'],
                'status' => $data['status'],
                'paid_at' => now()->setDate($data['year'], $data['month'], 15),
                'submitted_at' => now()->setDate($data['year'], $data['month'], 10),
                'created_by' => $user->id,
                'notes' => null,
            ];

            if ($data['status'] === ContributionPaymentStatus::Validated) {
                $attrs['validated_by'] = $data['validated_by'];
                $attrs['validated_at'] = now()->setDate($data['year'], $data['month'], 16);
            }

            if ($data['status'] === ContributionPaymentStatus::Rejected) {
                $attrs['rejected_by'] = $data['rejected_by'];
                $attrs['rejected_at'] = now()->setDate($data['year'], $data['month'], 14);
                $attrs['rejection_reason'] = $data['rejection_reason'];
            }

            ContributionPayment::updateOrCreate(
                ['user_id' => $user->id, 'reference' => $data['reference']],
                $attrs,
            );
        }
    }
}
