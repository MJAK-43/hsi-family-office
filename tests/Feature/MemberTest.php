<?php

namespace Tests\Feature;

use App\Models\MemberProfile;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FamilyBranchSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            CountrySeeder::class,
            FamilyBranchSeeder::class,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_guest_cannot_access_members(): void
    {
        $this->get(route('members.index'))->assertRedirect(route('login'));
    }

    public function test_member_without_permission_cannot_view_members(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('guest');

        $this->actingAs($user)
            ->get(route('members.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_members_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('members.index'))
            ->assertOk();
    }

    public function test_super_admin_can_create_member(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('members.store'), [
                'name' => 'Jean Test',
                'email' => 'jean.test@hsi-family-office.test',
                'password' => 'Mot-de-passe-solide-2026',
                'password_confirmation' => 'Mot-de-passe-solide-2026',
                'roles' => ['member'],
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'jean.test@hsi-family-office.test']);
        $this->assertDatabaseHas('member_profiles', ['status' => 'active']);
    }

    public function test_a_member_can_hold_several_roles(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['email_verified_at' => now()]);
        $target->assignRole('member');
        $profile = MemberProfile::create(['user_id' => $target->id, 'status' => 'active']);

        $this->actingAs($admin)
            ->put(route('members.update', $profile), [...$this->updatePayload($target, 'member'), 'roles' => ['member', 'treasurer', 'investment_committee']])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['member', 'treasurer', 'investment_committee'], $target->fresh()->getRoleNames()->all());
        $this->assertTrue($target->fresh()->can('cast votes'));

        // Depuis l'administration : on retire un rôle, les autres restent.
        $ids = \Spatie\Permission\Models\Role::whereIn('name', ['member', 'treasurer'])->pluck('id')->all();
        $this->actingAs($admin)->patch(route('admin.users.role', $target), ['role_ids' => $ids])->assertSessionHas('success');
        $this->assertEqualsCanonicalizing(['member', 'treasurer'], $target->fresh()->getRoleNames()->all());

        $this->actingAs($admin)->patch(route('admin.users.role', $target), ['role_ids' => []])->assertSessionHasErrors('role_ids');
        $this->actingAs($admin)->get(route('members.show', $profile))->assertOk()
            ->assertInertia(fn ($page) => $page->where('member.role', 'treasurer')->where('member.roles', ['treasurer', 'member']));
    }

    public function test_member_creation_is_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('members.store'), [
                'name' => 'Log Test',
                'email' => 'log.test@hsi-family-office.test',
                'password' => 'Mot-de-passe-solide-2026',
                'password_confirmation' => 'Mot-de-passe-solide-2026',
                'roles' => ['member'],
                'status' => 'active',
            ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Membre créé',
            'event' => 'member.created',
            'log_name' => 'members',
        ]);
    }

    public function test_secretary_cannot_assign_super_admin_role(): void
    {
        $secretary = User::factory()->create(['email_verified_at' => now()]);
        $secretary->assignRole('secretary');

        $this->actingAs($secretary)
            ->post(route('members.store'), [
                'name' => 'Escalation Test',
                'email' => 'escalation@hsi-family-office.test',
                'password' => 'Mot-de-passe-solide-2026',
                'password_confirmation' => 'Mot-de-passe-solide-2026',
                'roles' => ['super_admin'],
                'status' => 'active',
            ])
            ->assertSessionHasErrors('roles');
    }

    public function test_secretary_cannot_assign_treasurer_role(): void
    {
        $secretary = User::factory()->create(['email_verified_at' => now()]);
        $secretary->assignRole('secretary');

        $this->actingAs($secretary)
            ->post(route('members.store'), [
                'name' => 'Treasurer Escalation',
                'email' => 'treasurer-escalation@hsi-family-office.test',
                'password' => 'Mot-de-passe-solide-2026',
                'password_confirmation' => 'Mot-de-passe-solide-2026',
                'roles' => ['treasurer'],
                'status' => 'active',
            ])
            ->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'treasurer-escalation@hsi-family-office.test']);
    }

    public function test_user_cannot_change_their_own_role(): void
    {
        $admin = $this->admin();
        $profile = MemberProfile::create(['user_id' => $admin->id, 'status' => 'active']);

        $this->actingAs($admin)
            ->put(route('members.update', $profile), $this->updatePayload($admin, 'member'))
            ->assertSessionHasErrors('roles');

        $this->assertTrue($admin->fresh()->hasRole('super_admin'));
    }

    public function test_last_super_admin_cannot_be_demoted(): void
    {
        $target = $this->admin();
        $profile = MemberProfile::create(['user_id' => $target->id, 'status' => 'active']);
        $roleManager = User::factory()->create(['email_verified_at' => now()]);
        $roleManager->givePermissionTo(['edit members', 'manage roles']);

        $this->actingAs($roleManager)
            ->put(route('members.update', $profile), $this->updatePayload($target, 'member'))
            ->assertSessionHasErrors('roles');

        $this->assertTrue($target->fresh()->hasRole('super_admin'));
    }

    public function test_member_deletion_archives_without_deleting_identity(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('member');
        $profile = MemberProfile::create(['user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($admin)
            ->delete(route('members.destroy', $profile))
            ->assertRedirect(route('members.index'));

        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertDatabaseHas('member_profiles', [
            'id' => $profile->id,
            'status' => 'inactive',
            'deleted_at' => null,
        ]);
    }

    public function test_suspended_member_is_logged_out_before_private_access(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('member');
        MemberProfile::create(['user_id' => $member->id, 'status' => 'suspended']);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_super_admin_can_suspend_and_activate_member(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('member');
        $profile = MemberProfile::create([
            'user_id' => $member->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->patch(route('members.suspend', $profile))
            ->assertRedirect();

        $this->assertDatabaseHas('member_profiles', [
            'id' => $profile->id,
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Membre suspendu',
            'event' => 'member.suspended',
        ]);

        $this->actingAs($admin)
            ->patch(route('members.activate', $profile))
            ->assertRedirect();

        $this->assertDatabaseHas('member_profiles', [
            'id' => $profile->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Membre activé',
            'event' => 'member.activated',
        ]);
    }

    public function test_member_role_has_limited_permissions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('member');

        $this->assertTrue($user->can('view members'));
        $this->assertFalse($user->can('create members'));
        $this->assertFalse($user->can('edit members'));
        $this->assertFalse($user->can('suspend members'));
        $this->assertFalse($user->can('delete members'));
        $this->assertFalse($user->can('manage roles'));
    }

    private function updatePayload(User $user, string $role): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [$role],
            'status' => 'active',
        ];
    }
}
