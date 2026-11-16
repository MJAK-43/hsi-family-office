<?php

namespace Tests\Feature;

use App\Enums\ContributionPaymentStatus;
use App\Enums\PaymentMethod;
use App\Models\ContributionPayment;
use App\Models\ContributionPlan;
use App\Models\FinancialOperation;
use App\Models\FundAccount;
use App\Models\FundMovement;
use App\Models\PaymentProof;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FamilyBranchSeeder;
use Database\Seeders\FundAccountSeeder;
use Database\Seeders\FundAllocationRuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ContributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            CountrySeeder::class,
            FamilyBranchSeeder::class,
            FundAccountSeeder::class,
            FundAllocationRuleSeeder::class,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function createPlan(): ContributionPlan
    {
        return ContributionPlan::create([
            'name' => 'Plan test',
            'amount' => 20,
            'currency' => 'USD',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);
    }

    public function test_member_can_submit_contribution(): void
    {
        Storage::fake('local');
        $member = $this->userWithRole('member');
        $plan = $this->createPlan();

        $this->actingAs($member)
            ->post(route('contributions.store'), [
                'contribution_plan_id' => $plan->id,
                'amount' => 20,
                'currency' => 'USD',
                'payment_month' => 5,
                'payment_year' => 2025,
                'payment_method' => PaymentMethod::BankTransfer->value,
                'reference' => 'TEST-001',
                'proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contribution_payments', [
            'user_id' => $member->id,
            'reference' => 'TEST-001',
            'status' => ContributionPaymentStatus::Pending->value,
        ]);
    }

    public function test_member_cannot_see_other_members_contributions(): void
    {
        $member = $this->userWithRole('member');
        $other = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $other->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $other->id,
        ]);

        $this->actingAs($member)
            ->get(route('contributions.show', $payment))
            ->assertForbidden();
    }

    public function test_treasurer_can_view_all_contributions(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->actingAs($treasurer)
            ->get(route('contributions.index'))
            ->assertOk();

        $this->actingAs($treasurer)
            ->get(route('contributions.show', $payment))
            ->assertOk();
    }

    public function test_treasurer_can_validate_contribution(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->actingAs($treasurer)
            ->patch(route('contributions.validate', $payment))
            ->assertRedirect();

        $payment->refresh();
        $this->assertEquals(ContributionPaymentStatus::Validated, $payment->status);
        $this->assertEquals($treasurer->id, $payment->validated_by);
        $this->assertSame(4, FundMovement::where('contribution_payment_id', $payment->id)->count());
        $this->assertDatabaseHas('financial_operations', [
            'operation_type' => 'contribution_validation',
            'idempotency_key' => "contribution-validation:{$payment->id}",
            'status' => 'completed',
        ]);
    }

    public function test_validation_and_allocation_roll_back_together_on_failure(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');
        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => '20.00',
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);
        FundAccount::where('slug', 'education')->update(['currency' => 'EUR']);

        $this->actingAs($treasurer)
            ->patch(route('contributions.validate', $payment), [
                'idempotency_key' => 'validation-rollback-test',
            ])
            ->assertSessionHasErrors('validation');

        $payment->refresh();
        $this->assertSame(ContributionPaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->validated_at);
        $this->assertSame(0, FundMovement::where('contribution_payment_id', $payment->id)->count());
        $this->assertSame(0, FinancialOperation::where('idempotency_key', 'validation-rollback-test')->count());
        $this->assertDatabaseMissing('activity_log', [
            'event' => 'contribution.payment.validated',
            'subject_id' => $payment->id,
        ]);
    }

    public function test_validation_retry_with_same_key_does_not_duplicate_allocation_or_activity(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');
        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => '20.00',
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);
        $payload = ['idempotency_key' => 'validation-retry-test'];

        $this->actingAs($treasurer)->patch(route('contributions.validate', $payment), $payload)->assertRedirect();
        $this->actingAs($treasurer)->patch(route('contributions.validate', $payment), $payload)->assertRedirect();

        $this->assertSame(4, FundMovement::where('contribution_payment_id', $payment->id)->count());
        $this->assertSame(1, FinancialOperation::where('idempotency_key', 'validation-retry-test')->count());
        $this->assertSame(1, Activity::query()
            ->where('event', 'contribution.payment.validated')
            ->where('subject_id', $payment->id)
            ->count());
    }

    public function test_treasurer_can_reject_contribution(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->actingAs($treasurer)
            ->patch(route('contributions.reject', $payment), [
                'rejection_reason' => 'Preuve illisible, merci de resoumettre.',
            ])
            ->assertRedirect();

        $payment->refresh();
        $this->assertEquals(ContributionPaymentStatus::Rejected, $payment->status);
    }

    public function test_member_cannot_validate_own_contribution(): void
    {
        $member = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->actingAs($member)
            ->patch(route('contributions.validate', $payment))
            ->assertForbidden();
    }

    public function test_validated_payment_is_logged(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->actingAs($treasurer)
            ->patch(route('contributions.validate', $payment));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'contribution.payment.validated',
            'subject_type' => ContributionPayment::class,
            'subject_id' => $payment->id,
        ]);
    }

    public function test_payment_proofs_are_protected(): void
    {
        Storage::fake('local');
        $owner = $this->userWithRole('member');
        $other = $this->userWithRole('member');
        $treasurer = $this->userWithRole('treasurer');

        $payment = ContributionPayment::create([
            'user_id' => $owner->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $owner->id,
        ]);

        Storage::disk('local')->put('contribution-proofs/test.pdf', 'fake content');

        $proof = PaymentProof::create([
            'contribution_payment_id' => $payment->id,
            'original_name' => 'test.pdf',
            'file_name' => 'test.pdf',
            'file_path' => 'contribution-proofs/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('contributions.proofs.download', $proof))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('contributions.proofs.download', $proof))
            ->assertOk();

        $this->actingAs($treasurer)
            ->get(route('contributions.proofs.download', $proof))
            ->assertOk();
    }

    public function test_member_permissions_remain_limited(): void
    {
        $member = $this->userWithRole('member');

        $this->actingAs($member)
            ->get(route('contribution-plans.index'))
            ->assertForbidden();

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->actingAs($member)
            ->delete(route('contributions.destroy', $payment))
            ->assertForbidden();
    }

    public function test_treasurer_cannot_update_validated_payment(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Validated,
            'submitted_at' => now(),
            'validated_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->actingAs($treasurer)
            ->put(route('contributions.update', $payment), [
                'amount' => 50,
                'currency' => 'USD',
                'payment_method' => PaymentMethod::Cash->value,
                'user_id' => $member->id,
            ])
            ->assertForbidden();
    }

    public function test_member_cannot_update_validated_payment(): void
    {
        $member = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Validated,
            'submitted_at' => now(),
            'validated_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->actingAs($member)
            ->put(route('contributions.update', $payment), [
                'amount' => 50,
                'currency' => 'USD',
                'payment_method' => PaymentMethod::Cash->value,
            ])
            ->assertForbidden();
    }

    public function test_cannot_validate_rejected_payment_without_resubmit(): void
    {
        $treasurer = $this->userWithRole('treasurer');
        $member = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Rejected,
            'submitted_at' => now(),
            'rejected_at' => now(),
            'rejection_reason' => 'Preuve illisible.',
            'created_by' => $member->id,
        ]);

        $this->actingAs($treasurer)
            ->patch(route('contributions.validate', $payment))
            ->assertForbidden();
    }

    public function test_secretary_can_download_proof(): void
    {
        Storage::fake('local');
        $secretary = $this->userWithRole('secretary');
        $owner = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $owner->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $owner->id,
        ]);

        Storage::disk('local')->put('contribution-proofs/secretary-test.pdf', 'content');

        $proof = PaymentProof::create([
            'contribution_payment_id' => $payment->id,
            'original_name' => 'secretary-test.pdf',
            'file_name' => 'secretary-test.pdf',
            'file_path' => 'contribution-proofs/secretary-test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($secretary)
            ->get(route('contributions.proofs.download', $proof))
            ->assertOk();
    }

    public function test_president_cannot_validate_contribution(): void
    {
        $president = $this->userWithRole('president');
        $member = $this->userWithRole('member');

        $payment = ContributionPayment::create([
            'user_id' => $member->id,
            'amount' => 20,
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Cash,
            'status' => ContributionPaymentStatus::Pending,
            'submitted_at' => now(),
            'created_by' => $member->id,
        ]);

        $this->actingAs($president)
            ->patch(route('contributions.validate', $payment))
            ->assertForbidden();
    }
}
