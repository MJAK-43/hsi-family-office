<?php

namespace Tests\Feature;

use App\Enums\ContributionPaymentStatus;
use App\Enums\FundMovementStatus;
use App\Enums\FundMovementType;
use App\Enums\PaymentMethod;
use App\Exceptions\IdempotencyConflictException;
use App\Models\ContributionPayment;
use App\Models\FinancialOperation;
use App\Models\FundAccount;
use App\Models\FundAllocationRule;
use App\Models\FundMovement;
use App\Models\User;
use App\Services\FundService;
use App\ValueObjects\Money;
use Database\Seeders\FundAccountSeeder;
use Database\Seeders\FundAllocationRuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            FundAccountSeeder::class,
            FundAllocationRuleSeeder::class,
        ]);
        // Mouvements enregistrés directement : le circuit de validation est testé dans RegulationWorkflowTest.
        \App\Models\TransactionType::query()->update(['required_approvals' => 1]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function validatedPayment(User $member, int|string $amount = 100, string $currency = 'USD'): ContributionPayment
    {
        return ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => $amount,
            'currency' => $currency,
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Validated,
            'submitted_at' => now(),
            'validated_at' => now(),
            'created_by' => $member->id,
        ]);
    }

    public function test_validated_contribution_creates_allocation_movements(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');
        $payment = $this->validatedPayment($member, 100);

        app(FundService::class)->allocateContributionPayment($payment, $treasurer);

        $this->assertDatabaseHas('fund_movements', [
            'contribution_payment_id' => $payment->id,
            'type' => FundMovementType::ContributionAllocation->value,
            'direction' => 'credit',
        ]);

        $this->assertEquals(4, FundMovement::where('contribution_payment_id', $payment->id)->count());
    }

    public function test_already_allocated_contribution_does_not_duplicate(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');
        $payment = $this->validatedPayment($member, 100);
        $service = app(FundService::class);

        $service->allocateContributionPayment($payment, $treasurer);
        $service->allocateContributionPayment($payment, $treasurer);

        $this->assertEquals(4, FundMovement::where('contribution_payment_id', $payment->id)->count());
    }

    public function test_allocation_respects_default_percentages(): void
    {
        $member = $this->userWithRole('member');
        $payment = $this->validatedPayment($member, 100);

        app(FundService::class)->allocateContributionPayment($payment);

        $longTerm = FundAccount::where('slug', 'long-term-investment')->first();
        $movement = FundMovement::where('contribution_payment_id', $payment->id)
            ->where('fund_account_id', $longTerm->id)
            ->first();

        $this->assertEquals(40.0, (float) $movement->amount);
    }

    public function test_member_can_view_funds(): void
    {
        $member = $this->userWithRole('member');

        $this->actingAs($member)
            ->get(route('funds.index'))
            ->assertOk();
    }

    public function test_member_cannot_create_movement(): void
    {
        $member = $this->userWithRole('member');
        $account = FundAccount::first();

        $this->actingAs($member)
            ->post(route('funds.movements.store'), [
                'fund_account_id' => $account->id,
                'type' => FundMovementType::ManualIncome->value,
                'amount' => 50,
                'currency' => 'USD',
                'direction' => 'credit',
                'label' => 'Test',
            ])
            ->assertForbidden();
    }

    public function test_treasurer_can_create_manual_movement(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $account = FundAccount::where('slug', 'treasury')->first();

        $this->actingAs($treasurer)
            ->post(route('funds.movements.store'), [
                'fund_account_id' => $account->id,
                'type' => FundMovementType::ManualIncome->value,
                'amount' => 50,
                'currency' => 'USD',
                'direction' => 'credit',
                'label' => 'Don exceptionnel',
            ])
            ->assertRedirect(route('funds.movements'));

        $account->refresh();
        $this->assertEquals(50.0, (float) $account->current_balance);
    }

    public function test_treasurer_can_transfer_between_accounts(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $from = FundAccount::where('slug', 'treasury')->first();
        $to = FundAccount::where('slug', 'education')->first();

        app(FundService::class)->createMovement([
            'fund_account_id' => $from->id,
            'type' => FundMovementType::ManualIncome,
            'amount' => 100,
            'currency' => 'USD',
            'direction' => 'credit',
            'label' => 'Apport trésorerie',
            'created_by' => $treasurer->id,
            'validated_by' => $treasurer->id,
        ]);

        $this->actingAs($treasurer)
            ->post(route('funds.transfer'), [
                'from_fund_account_id' => $from->id,
                'to_fund_account_id' => $to->id,
                'amount' => 30,
                'description' => 'Transfert vers éducation pour bourses.',
            ])
            ->assertRedirect(route('funds.index'));

        $from->refresh();
        $to->refresh();
        $this->assertEquals(70.0, (float) $from->current_balance);
        $this->assertEquals(30.0, (float) $to->current_balance);
    }

    public function test_transfer_retry_with_same_key_returns_existing_movements(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $from = FundAccount::where('slug', 'treasury')->firstOrFail();
        $to = FundAccount::where('slug', 'education')->firstOrFail();
        $service = app(FundService::class);
        $service->createMovement([
            'fund_account_id' => $from->id,
            'type' => FundMovementType::ManualIncome,
            'amount' => '100.00',
            'currency' => 'USD',
            'direction' => 'credit',
            'label' => 'Apport idempotence',
            'created_by' => $treasurer->id,
            'validated_by' => $treasurer->id,
        ]);

        $first = $service->transfer($from, $to, '30.00', 'Transfert idempotent', $treasurer, 'transfer-retry-test');
        $second = $service->transfer($from, $to, '30.00', 'Transfert idempotent', $treasurer, 'transfer-retry-test');

        $this->assertSame([$first[0]->id, $first[1]->id], [$second[0]->id, $second[1]->id]);
        $this->assertSame(2, FundMovement::where('financial_operation_id', $first[0]->financial_operation_id)->count());
        $this->assertSame('70.00', $from->fresh()->current_balance);
        $this->assertSame('30.00', $to->fresh()->current_balance);
        $this->assertSame(1, FinancialOperation::where('idempotency_key', 'transfer-retry-test')->count());
    }

    public function test_transfer_rejects_same_key_with_different_payload(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $from = FundAccount::where('slug', 'treasury')->firstOrFail();
        $to = FundAccount::where('slug', 'education')->firstOrFail();
        $service = app(FundService::class);
        $service->createMovement([
            'fund_account_id' => $from->id,
            'type' => FundMovementType::ManualIncome,
            'amount' => '100.00',
            'currency' => 'USD',
            'direction' => 'credit',
            'label' => 'Apport conflit',
            'created_by' => $treasurer->id,
            'validated_by' => $treasurer->id,
        ]);
        $service->transfer($from, $to, '30.00', 'Payload initial', $treasurer, 'transfer-conflict-test');

        $this->expectException(IdempotencyConflictException::class);

        $service->transfer($from, $to, '31.00', 'Payload modifié', $treasurer, 'transfer-conflict-test');
    }

    public function test_transfer_fails_with_insufficient_balance(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $from = FundAccount::where('slug', 'treasury')->first();
        $to = FundAccount::where('slug', 'education')->first();

        $this->actingAs($treasurer)
            ->post(route('funds.transfer'), [
                'from_fund_account_id' => $from->id,
                'to_fund_account_id' => $to->id,
                'amount' => 1000,
                'description' => 'Transfert impossible faute de solde.',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_cancelling_movement_updates_balance(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $account = FundAccount::where('slug', 'treasury')->first();
        $service = app(FundService::class);

        $movement = $service->createMovement([
            'fund_account_id' => $account->id,
            'type' => FundMovementType::ManualIncome,
            'amount' => 75,
            'currency' => 'USD',
            'direction' => 'credit',
            'label' => 'À annuler',
            'created_by' => $treasurer->id,
            'validated_by' => $treasurer->id,
        ]);

        $account->refresh();
        $this->assertEquals(75.0, (float) $account->current_balance);

        $service->cancelMovement($movement, 'Erreur de saisie du montant.', $treasurer);

        $account->refresh();
        $this->assertEquals(0.0, (float) $account->current_balance);
        $this->assertEquals(FundMovementStatus::Cancelled, $movement->fresh()->status);
    }

    public function test_allocation_rule_percentages_must_sum_to_100(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $accounts = FundAccount::where('slug', '!=', 'treasury')->take(2)->pluck('id');

        $this->actingAs($treasurer)
            ->post(route('fund-allocation-rules.store'), [
                'name' => 'Règle invalide',
                'is_active' => true,
                'items' => [
                    ['fund_account_id' => $accounts[0], 'percentage' => 50],
                    ['fund_account_id' => $accounts[1], 'percentage' => 30],
                ],
            ])
            ->assertSessionHasErrors('items');
    }

    public function test_only_one_rule_can_be_default(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $accounts = FundAccount::where('slug', '!=', 'treasury')->get();

        $this->actingAs($treasurer)
            ->post(route('fund-allocation-rules.store'), [
                'name' => 'Règle alternative',
                'is_default' => true,
                'is_active' => true,
                'items' => $accounts->map(fn ($a) => [
                    'fund_account_id' => $a->id,
                    'percentage' => 25,
                ])->all(),
            ])
            ->assertRedirect();

        $defaults = FundAllocationRule::where('is_default', true)->count();
        $this->assertEquals(1, $defaults);
    }

    public function test_pending_contribution_cannot_be_allocated(): void
    {
        $member = $this->userWithRole('member');
        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 100,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->expectException(\RuntimeException::class);
        app(FundService::class)->allocateContributionPayment($payment);
    }

    public function test_member_cannot_transfer_funds(): void
    {
        $member = $this->userWithRole('member');
        $from = FundAccount::where('slug', 'treasury')->first();
        $to = FundAccount::where('slug', 'education')->first();

        $this->actingAs($member)
            ->post(route('funds.transfer'), [
                'from_fund_account_id' => $from->id,
                'to_fund_account_id' => $to->id,
                'amount' => 10,
                'description' => 'Tentative de transfert non autorisée.',
            ])
            ->assertForbidden();
    }

    public function test_treasurer_cannot_create_fake_allocation_movement(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $account = FundAccount::first();

        $this->actingAs($treasurer)
            ->post(route('funds.movements.store'), [
                'fund_account_id' => $account->id,
                'type' => FundMovementType::ContributionAllocation->value,
                'amount' => 50,
                'currency' => 'USD',
                'direction' => 'credit',
                'label' => 'Fausse allocation',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_manual_debit_blocked_with_insufficient_balance(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $account = FundAccount::where('slug', 'treasury')->first();

        $this->actingAs($treasurer)
            ->post(route('funds.movements.store'), [
                'fund_account_id' => $account->id,
                'type' => FundMovementType::ManualExpense->value,
                'amount' => 100,
                'currency' => 'USD',
                'direction' => 'debit',
                'label' => 'Sortie impossible',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_allocation_amounts_sum_to_payment_total(): void
    {
        $member = $this->userWithRole('member');
        $payment = $this->validatedPayment($member, '33.33');

        app(FundService::class)->allocateContributionPayment($payment);

        $total = FundMovement::where('contribution_payment_id', $payment->id)->sum('amount');
        $this->assertEquals(33.33, (float) $total);
    }

    public function test_allocation_uses_exact_centimes_for_largest_remainders(): void
    {
        $member = $this->userWithRole('member');
        $payment = $this->validatedPayment($member, '0.03');
        $rule = FundAllocationRule::where('is_default', true)->firstOrFail();
        $items = $rule->items()->orderBy('id')->get();

        foreach ($items as $index => $item) {
            $item->update(['percentage' => [25, 25, 25, 25][$index]]);
        }

        app(FundService::class)->allocateContributionPayment($payment);

        $amounts = FundMovement::where('contribution_payment_id', $payment->id)
            ->orderBy('fund_account_id')
            ->pluck('amount')
            ->all();

        $this->assertSame(['0.01', '0.01', '0.01'], $amounts);

        $total = collect($amounts)->reduce(
            fn (Money $carry, string $amount): Money => $carry->add(Money::fromDecimal($amount, 'USD')),
            Money::zero('USD'),
        );

        $this->assertSame('0.03', $total->decimal());
    }

    public function test_allocation_rejects_payment_currency_different_from_accounts(): void
    {
        $member = $this->userWithRole('member');
        $payment = $this->validatedPayment($member, '100.00', 'EUR');

        $this->expectException(\RuntimeException::class);

        app(FundService::class)->allocateContributionPayment($payment);
    }

    public function test_manual_movement_rejects_currency_different_from_account(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $account = FundAccount::where('slug', 'treasury')->firstOrFail();

        $this->actingAs($treasurer)
            ->post(route('funds.movements.store'), [
                'fund_account_id' => $account->id,
                'type' => FundMovementType::ManualIncome->value,
                'amount' => '10.00',
                'currency' => 'EUR',
                'direction' => 'credit',
                'label' => 'Devise incompatible',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('fund_movements', ['label' => 'Devise incompatible']);
    }

    public function test_transfer_rejects_accounts_with_different_currencies(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $from = FundAccount::where('slug', 'treasury')->firstOrFail();
        $to = FundAccount::where('slug', 'education')->firstOrFail();
        $to->update(['currency' => 'EUR']);

        $this->expectException(\InvalidArgumentException::class);

        app(FundService::class)->transfer($from, $to, '10.00', 'Devise incompatible', $treasurer);
    }

    public function test_cancelling_transfer_cancels_both_legs(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $from = FundAccount::where('slug', 'treasury')->first();
        $to = FundAccount::where('slug', 'education')->first();
        $service = app(FundService::class);

        $service->createMovement([
            'fund_account_id' => $from->id,
            'type' => FundMovementType::ManualIncome,
            'amount' => 100,
            'currency' => 'USD',
            'direction' => 'credit',
            'label' => 'Apport',
            'created_by' => $treasurer->id,
            'validated_by' => $treasurer->id,
        ]);

        [$out] = $service->transfer($from, $to, 40, 'Transfert test annulation', $treasurer);

        $service->cancelMovement($out, 'Annulation du transfert complet.', $treasurer);

        $from->refresh();
        $to->refresh();
        $this->assertEquals(100.0, (float) $from->current_balance);
        $this->assertEquals(0.0, (float) $to->current_balance);
        $this->assertEquals(2, FundMovement::where('reference', $out->reference)->where('status', FundMovementStatus::Cancelled)->count());
    }
}
