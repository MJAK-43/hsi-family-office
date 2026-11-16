<?php

namespace Tests\Feature;

use App\Models\ContributionPayment;
use App\Models\MemberProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_role_does_not_receive_private_dashboard_widgets(): void
    {
        $guest = $this->activeUser('Guest User', 'guest');

        $this->actingAs($guest)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('visibility.members', false)
                ->where('visibility.contributions', false)
                ->where('visibility.funds', false)
                ->where('visibility.projects', false)
                ->where('visibility.votes', false)
                ->has('recentContributions', 0)
                ->has('recentProjects', 0)
                ->has('recentVotes', 0));
    }

    public function test_member_dashboard_only_contains_their_contributions(): void
    {
        $member = $this->activeUser('Visible Member', 'member');
        $other = $this->activeUser('Hidden Member', 'member');

        $this->validatedPayment($member, 20);
        $this->validatedPayment($other, 900);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visibility.contributions', true)
                ->where('contributionStats.validatedCount', 1)
                ->has('recentContributions', 1)
                ->where('recentContributions.0.membre', 'Visible Member'));
    }

    public function test_authenticated_user_roles_and_permissions_are_shared_with_the_frontend(): void
    {
        $member = $this->activeUser('Visible Member', 'member');

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles.0', 'member')
                ->where('auth.permissions', fn ($permissions) => $permissions->contains('view dashboard')
                    && $permissions->contains('view members')));
    }

    private function activeUser(string $name, string $role): User
    {
        $user = User::factory()->create(['name' => $name, 'email_verified_at' => now()]);
        $user->assignRole($role);
        MemberProfile::create(['user_id' => $user->id, 'status' => 'active']);

        return $user;
    }

    private function validatedPayment(User $user, int $amount): void
    {
        ContributionPayment::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'status' => 'validated',
            'submitted_at' => now(),
            'validated_at' => now(),
        ]);
    }
}
