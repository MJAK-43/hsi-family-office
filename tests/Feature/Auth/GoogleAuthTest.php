<?php

namespace Tests\Feature\Auth;

use App\Enums\MemberStatus;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Models\Country;
use App\Models\MemberProfile;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\Membership\ApplicationDecision;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
        ]);
    }

    private function fakeGoogle(string $email, string $id = 'google-123', bool $verified = true): void
    {
        $user = (new SocialiteUser)->setRaw([
            'sub' => $id,
            'email' => $email,
            'email_verified' => $verified,
            'given_name' => 'Awa',
            'family_name' => 'Jokung',
        ])->map(['id' => $id, 'email' => $email, 'name' => 'Awa Jokung']);

        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_buttons_are_offered_only_when_google_is_configured(): void
    {
        $this->get('/login')->assertInertia(fn ($page) => $page->where('googleEnabled', true));
        $this->get('/adhesion')->assertInertia(fn ($page) => $page->where('googleEnabled', true)->where('google', null));

        config(['services.google.client_id' => null]);
        $this->get('/login')->assertInertia(fn ($page) => $page->where('googleEnabled', false));
        $this->get('/auth/google')->assertRedirect(route('login'));
    }

    public function test_an_existing_member_is_signed_in_and_linked(): void
    {
        $user = User::factory()->create(['email' => 'awa.jokung@example.com']);
        $this->fakeGoogle('Awa.Jokung@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->fresh()->google_id);
    }

    public function test_an_unverified_google_address_is_refused(): void
    {
        User::factory()->create(['email' => 'awa.jokung@example.com']);
        $this->fakeGoogle('awa.jokung@example.com', verified: false);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_account_linked_to_another_google_is_not_taken_over(): void
    {
        User::factory()->create(['email' => 'awa.jokung@example.com'])->forceFill(['google_id' => 'other'])->save();
        $this->fakeGoogle('awa.jokung@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_suspended_member_signing_in_with_google_is_logged_out(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['email' => 'awa.jokung@example.com']);
        $user->assignRole('member');
        MemberProfile::create(['user_id' => $user->id, 'status' => MemberStatus::Suspended->value]);
        $this->fakeGoogle('awa.jokung@example.com');

        $this->get('/auth/google/callback');
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_unknown_person_lands_on_a_prefilled_application_without_password(): void
    {
        Notification::fake();
        $country = Country::create(['code' => 'FR', 'name' => 'France', 'is_active' => true]);
        $this->fakeGoogle('awa.jokung@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('membership.apply'));
        $this->assertGuest();

        $this->get('/adhesion')->assertInertia(fn ($page) => $page
            ->where('google.email', 'awa.jokung@example.com')
            ->where('google.first_name', 'Awa')
            ->missing('google.google_id'));

        // L'email envoyé par le formulaire est ignoré : c'est celui vérifié par Google qui compte.
        $this->post('/adhesion', [
            'first_name' => 'Awa',
            'last_name' => 'Jokung',
            'email' => 'pirate@example.com',
            'phone' => '+33612345678',
            'country_id' => $country->id,
            'city' => 'Lyon',
            'consent' => true,
        ])->assertRedirect(route('membership.sent'));

        $application = MembershipApplication::sole();
        $this->assertSame('awa.jokung@example.com', $application->email);
        $this->assertSame('google-123', $application->google_id);
        $this->assertNull($application->password);
        $this->assertNull(session(GoogleAuthController::SESSION_KEY));

        // Acceptation : le compte est créé et relié à Google.
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->patch(route('membership-applications.decide', $application), ['decision' => 'accepted'])
            ->assertSessionHas('success');

        $user = User::where('email', 'awa.jokung@example.com')->sole();
        $this->assertSame('google-123', $user->google_id);
        Notification::assertSentOnDemand(ApplicationDecision::class);
    }

    public function test_a_pending_applicant_is_told_to_wait(): void
    {
        $country = Country::create(['code' => 'FR', 'name' => 'France', 'is_active' => true]);
        MembershipApplication::create([
            'first_name' => 'Awa', 'last_name' => 'Jokung', 'email' => 'awa.jokung@example.com',
            'country_id' => $country->id, 'country' => 'France', 'status' => MembershipApplication::STATUS_PENDING,
        ]);
        $this->fakeGoogle('awa.jokung@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('status');
        $this->assertNull(session(GoogleAuthController::SESSION_KEY));
    }
}
