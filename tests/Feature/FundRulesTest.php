<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\MemberProfile;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Zone;
use App\Support\FundRules;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FundRules::flush();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        FundRules::flush();
        parent::tearDown();
    }

    private function userWithRole(string ...$roles): User
    {
        $user = User::factory()->create();
        $user->syncRoles($roles);
        MemberProfile::create(['user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    public function test_the_rules_zones_and_transaction_types_are_installed_in_dollars(): void
    {
        $this->assertSame(80.0, FundRules::get('vote_approval_percent'));
        $this->assertSame(4.0, FundRules::get('loan_annual_interest_rate'));
        $this->assertSame(2.0, FundRules::get('contribution_penalty_amount'));
        $this->assertSame(16, FundRules::get('contribution_penalty_start_day'));
        $this->assertSame(7, FundRules::get('contribution_penalty_period_days'));
        $this->assertSame('USD', ApplicationSetting::where('key', 'default_currency')->value('value'));
        $this->assertEqualsCanonicalizing(['europe', 'america'], Zone::pluck('code')->all());
        $this->assertSame(2, TransactionType::where('code', 'loan_disbursement')->value('required_approvals'));
    }

    public function test_a_new_member_must_accept_the_internal_rules_before_using_the_app(): void
    {
        $user = User::factory()->unacceptedPolicy()->create();
        $user->assignRole('member');
        MemberProfile::create(['user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('policies.show'));
        $this->actingAs($user)->get(route('members.index'))->assertRedirect(route('policies.show'));
        $this->actingAs($user)->get(route('policies.show'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Policies/Show')->where('mustAccept', true)->has('sections', 10));

        // Sans cocher la case : refusé.
        $this->actingAs($user)->post(route('policies.accept'), [])->assertSessionHasErrors('accepted');
        $this->assertNull($user->fresh()->policy_accepted_at);

        // Après acceptation, on revient sur la page demandée au départ.
        $this->actingAs($user)->post(route('policies.accept'), ['accepted' => true])->assertRedirect(route('members.index'));
        $user->refresh();
        $this->assertNotNull($user->policy_accepted_at);
        $this->assertSame('2026-11', $user->policy_accepted_version);
        $this->assertDatabaseHas('activity_log', ['event' => 'policy.accepted', 'subject_id' => $user->id]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_a_new_version_of_the_rules_must_be_accepted_again(): void
    {
        $admin = $this->userWithRole('super_admin');
        $member = $this->userWithRole('member');

        $rules = collect(FundRules::all())->mapWithKeys(fn ($rule) => [$rule['key'] => $rule['value']])->all();
        $this->actingAs($admin)->put(route('fund-settings.rules.update'), ['rules' => [...$rules, 'policy_version' => '2027-01']])
            ->assertSessionHas('success');
        FundRules::flush();

        $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('policies.show'));
    }

    public function test_the_administrator_can_change_the_rules_with_validation_and_history(): void
    {
        $admin = $this->userWithRole('super_admin');
        $rules = collect(FundRules::all())->mapWithKeys(fn ($rule) => [$rule['key'] => $rule['value']])->all();

        $this->actingAs($admin)->get(route('fund-settings.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('FundSettings/Index')->where('can.rules', true)->has('zones', 2));

        $this->actingAs($admin)->put(route('fund-settings.rules.update'), ['rules' => [...$rules, 'vote_approval_percent' => 120]])
            ->assertSessionHasErrors('rules.vote_approval_percent');
        $this->actingAs($admin)->put(route('fund-settings.rules.update'), ['rules' => [...$rules, 'loan_third_reminder_days' => 10]])
            ->assertSessionHasErrors('rules.loan_third_reminder_days');

        $this->actingAs($admin)->put(route('fund-settings.rules.update'), ['rules' => [...$rules, 'loan_annual_interest_rate' => '4.5']])
            ->assertSessionHas('success');
        FundRules::flush();
        $this->assertSame(4.5, FundRules::get('loan_annual_interest_rate'));
        $this->assertDatabaseHas('activity_log', ['event' => 'fund_rules.updated']);
    }

    public function test_a_treasurer_manages_transaction_types_but_not_the_rules(): void
    {
        $treasurer = $this->userWithRole('treasurer', 'member');
        $rules = collect(FundRules::all())->mapWithKeys(fn ($rule) => [$rule['key'] => $rule['value']])->all();

        $this->actingAs($treasurer)->get(route('fund-settings.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.rules', false)->where('can.transactionTypes', true)->where('can.zones', false));
        $this->actingAs($treasurer)->put(route('fund-settings.rules.update'), ['rules' => $rules])->assertForbidden();
        $this->actingAs($treasurer)->post(route('fund-settings.zones.store'), ['name' => 'Afrique'])->assertForbidden();

        $this->actingAs($treasurer)->post(route('fund-settings.transaction-types.store'), [
            'name' => 'Frais bancaires', 'direction' => 'out', 'required_approvals' => 2,
            'approver_roles' => ['treasurer'], 'is_active' => true,
        ])->assertSessionHas('success');
        $type = TransactionType::where('code', 'frais_bancaires')->firstOrFail();
        $this->assertSame(2, $type->required_approvals);
        $this->assertTrue($type->canBeApprovedBy($treasurer));
        $this->assertFalse($type->canBeApprovedBy($this->userWithRole('member')));

        // Un type prévu par le règlement se modifie (nombre de validations) mais ne se supprime pas, et garde son sens.
        $disbursement = TransactionType::where('code', 'loan_disbursement')->firstOrFail();
        $this->actingAs($treasurer)->put(route('fund-settings.transaction-types.update', $disbursement), [
            'name' => $disbursement->name, 'direction' => 'in', 'required_approvals' => 1,
            'approver_roles' => ['treasurer', 'president'], 'is_active' => true,
        ])->assertSessionHas('success');
        $this->assertSame(1, $disbursement->fresh()->required_approvals);
        $this->assertSame('out', $disbursement->fresh()->direction);
        $this->actingAs($treasurer)->delete(route('fund-settings.transaction-types.destroy', $disbursement))->assertSessionHas('error');
        $this->assertModelExists($disbursement);

        $this->actingAs($this->userWithRole('member'))->get(route('fund-settings.index'))->assertForbidden();
    }

    public function test_zones_can_have_no_one_or_several_treasurers(): void
    {
        $president = $this->userWithRole('president');
        $first = $this->userWithRole('treasurer');
        $second = $this->userWithRole('treasurer', 'investment_committee');
        $member = $this->userWithRole('member');

        $this->actingAs($president)->post(route('fund-settings.zones.store'), ['name' => 'Afrique', 'is_active' => true])->assertSessionHas('success');
        $africa = Zone::where('name', 'Afrique')->firstOrFail();
        $this->assertSame(0, $africa->treasurers()->count());

        $this->actingAs($president)->put(route('fund-settings.zones.update', $africa), ['name' => 'Afrique', 'is_active' => true, 'treasurer_ids' => [$first->id, $second->id]])
            ->assertSessionHas('success');
        $this->assertSame(2, $africa->treasurers()->count());

        // Seule une personne qui a le rôle Trésorier peut être trésorière d'une zone.
        $this->actingAs($president)->put(route('fund-settings.zones.update', $africa), ['name' => 'Afrique', 'is_active' => true, 'treasurer_ids' => [$member->id]])
            ->assertSessionHasErrors('treasurer_ids.0');

        MemberProfile::where('user_id', $member->id)->update(['zone_id' => $africa->id]);
        $this->actingAs($president)->delete(route('fund-settings.zones.destroy', $africa))->assertSessionHas('error');
        $this->assertModelExists($africa);
    }
}
