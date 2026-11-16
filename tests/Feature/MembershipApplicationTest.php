<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\Membership\ApplicationDecision;
use App\Notifications\Membership\ApplicationReceived;
use App\Notifications\Membership\ExistingAccountNotice;
use App\Notifications\Membership\NewApplicationForAdmin;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MembershipApplicationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Heritage-Familial-2026';

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::create(['code' => 'FR', 'name' => 'France', 'is_active' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Awa',
            'last_name' => 'Jokung',
            'email' => 'awa.jokung@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'phone' => '+33612345678',
            'country_id' => $this->country->id,
            'city' => 'Lyon',
            'address' => '3 rue des Lilas',
            'profession' => 'Ingénieure',
            'date_of_birth' => '1995-04-12',
            'consent' => true,
            'website' => '',
        ], $overrides);
    }

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    public function test_failed_attempts_do_not_count_towards_the_limit(): void
    {
        Notification::fake();

        // Dix essais avec des erreurs : aucun n'est bloqué par la limite.
        for ($i = 0; $i < 10; $i++) {
            $this->post('/adhesion', $this->payload(['phone' => '123']))->assertSessionHasErrors('phone');
        }

        $this->post('/adhesion', $this->payload())->assertRedirect(route('membership.sent'));
        $this->assertDatabaseCount('membership_applications', 1);

        // Au-delà de cinq demandes complètes en une heure, la sixième est refusée avec un message clair.
        for ($i = 2; $i <= 5; $i++) {
            $this->post('/adhesion', $this->payload(['email' => "personne$i@example.com"]))->assertRedirect(route('membership.sent'));
        }
        $this->post('/adhesion', $this->payload(['email' => 'personne6@example.com']))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('membership_applications', 5);
    }

    public function test_too_many_submissions_show_a_clear_message_instead_of_a_429_page(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->from('/adhesion')->post('/adhesion', $this->payload(['phone' => '123']));
        }

        $this->from('/adhesion')->post('/adhesion', $this->payload(['phone' => '123']), ['X-Inertia' => 'true'])
            ->assertRedirect('/adhesion')
            ->assertSessionHas('error', fn ($message) => str_contains($message, ' 1 minute'));
    }

    public function test_the_application_page_is_public(): void
    {
        $this->get('/adhesion')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_a_valid_application_is_saved_without_creating_an_account(): void
    {
        Notification::fake();
        config(['mail.admin_address' => 'admin@example.org']);

        $this->post('/adhesion', $this->payload())->assertRedirect(route('membership.sent'));

        $application = MembershipApplication::firstOrFail();
        $this->assertSame('pending', $application->status);
        $this->assertSame('France', $application->country);
        $this->assertTrue(Hash::check(self::PASSWORD, $application->password));
        $this->assertDatabaseMissing('users', ['email' => 'awa.jokung@example.com']);

        Notification::assertSentOnDemand(ApplicationReceived::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'awa.jokung@example.com');
        Notification::assertSentOnDemand(NewApplicationForAdmin::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'admin@example.org');
    }

    public function test_required_fields_password_and_consent_are_validated(): void
    {
        $this->post('/adhesion', $this->payload([
            'first_name' => '',
            'email' => 'pas-un-email',
            'consent' => false,
            'phone' => '',
            'country_id' => 999,
            'password' => 'court',
            'password_confirmation' => 'autre',
        ]))->assertSessionHasErrors(['first_name', 'email', 'consent', 'phone', 'country_id', 'password']);

        $this->assertDatabaseCount('membership_applications', 0);
    }

    public function test_the_phone_must_be_in_international_format(): void
    {
        // Plusieurs envois d'affilée : on neutralise la limite de 5 demandes par heure.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        foreach (['06 12 34 56 78', '0612345678', '+33 6 12 34 56 78', '+0612345678', '+33'] as $phone) {
            $this->post('/adhesion', $this->payload(['phone' => $phone]))->assertSessionHasErrors('phone');
        }

        Notification::fake();
        $this->post('/adhesion', $this->payload(['phone' => '+237670123456']))->assertSessionHasNoErrors();
        $this->assertSame('+237670123456', MembershipApplication::firstOrFail()->phone);
    }

    public function test_an_existing_member_gets_the_same_answer_without_a_new_application(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'membre@example.com']);

        $this->post('/adhesion', $this->payload(['email' => 'Membre@Example.com']))->assertRedirect(route('membership.sent'));

        $this->assertDatabaseCount('membership_applications', 0);
        Notification::assertSentTo($user, ExistingAccountNotice::class);
    }

    public function test_a_duplicate_pending_application_is_not_created_twice(): void
    {
        Notification::fake();
        $this->post('/adhesion', $this->payload());
        $this->post('/adhesion', $this->payload());

        $this->assertDatabaseCount('membership_applications', 1);
    }

    public function test_the_honeypot_silently_drops_bots(): void
    {
        Notification::fake();
        $this->post('/adhesion', $this->payload(['website' => 'http://spam.example']))->assertRedirect(route('membership.sent'));

        $this->assertDatabaseCount('membership_applications', 0);
        Notification::assertNothingSent();
    }

    public function test_accepting_creates_the_member_account_with_the_chosen_password(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->post('/adhesion', $this->payload());
        $application = MembershipApplication::firstOrFail();

        $this->actingAs($admin)->get(route('membership-applications.index'))->assertOk();
        $this->actingAs($admin)
            ->patch(route('membership-applications.decide', $application), ['decision' => 'accepted', 'decision_note' => 'Bienvenue !'])
            ->assertSessionHas('success');

        $user = User::where('email', 'awa.jokung@example.com')->firstOrFail();
        $this->assertSame('Awa Jokung', $user->name);
        $this->assertTrue($user->hasRole('member'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));

        $profile = $user->memberProfile;
        $this->assertSame('active', $profile->status->value);
        $this->assertSame($this->country->id, $profile->country_id);
        $this->assertSame('Lyon', $profile->city);
        $this->assertSame('Ingénieure', $profile->profession);

        $application->refresh();
        $this->assertSame('accepted', $application->status);
        $this->assertSame($user->id, $application->user_id);
        $this->assertNull($application->password);
        Notification::assertSentOnDemand(ApplicationDecision::class);

        // Le nouveau membre peut se connecter avec le mot de passe choisi dans sa demande.
        auth()->logout();
        $this->post('/login', ['email' => 'awa.jokung@example.com', 'password' => self::PASSWORD]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_english_speaking_applicant_receives_emails_in_english(): void
    {
        Notification::fake();
        config(['mail.admin_address' => 'admin@example.org']);
        $admin = $this->admin();

        // Visiteur aux États-Unis : interface et emails en anglais.
        $this->withHeaders(['CF-IPCountry' => 'US'])->post('/adhesion', $this->payload());
        $application = MembershipApplication::firstOrFail();
        $this->assertSame('en', $application->locale);

        Notification::assertSentOnDemand(ApplicationReceived::class, function ($n, $channels, $notifiable) {
            app()->setLocale('en');

            return $n->toMail($notifiable)->subject === 'Your membership application has been received — HSI';
        });
        // L'administrateur, lui, reçoit l'alerte en français.
        Notification::assertSentOnDemand(NewApplicationForAdmin::class, fn ($n) => $n->locale === 'fr');

        $this->actingAs($admin)->withHeaders(['CF-IPCountry' => 'FR'])
            ->patch(route('membership-applications.decide', $application), ['decision' => 'accepted']);

        Notification::assertSentOnDemand(ApplicationDecision::class, fn ($n) => $n->locale === 'en');
        $this->assertSame('en', User::where('email', 'awa.jokung@example.com')->value('locale'));
    }

    public function test_refusing_creates_no_account_and_forgets_the_password(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->post('/adhesion', $this->payload());
        $application = MembershipApplication::firstOrFail();

        $this->actingAs($admin)
            ->patch(route('membership-applications.decide', $application), ['decision' => 'refused'])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['email' => 'awa.jokung@example.com']);
        $application->refresh();
        $this->assertSame('refused', $application->status);
        $this->assertNull($application->password);
        $this->assertNull($application->user_id);
        Notification::assertSentOnDemand(ApplicationDecision::class);
    }

    public function test_an_application_cannot_be_decided_twice(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->post('/adhesion', $this->payload());
        $application = MembershipApplication::firstOrFail();

        $this->actingAs($admin)->patch(route('membership-applications.decide', $application), ['decision' => 'accepted']);
        $this->actingAs($admin)->patch(route('membership-applications.decide', $application), ['decision' => 'accepted'])
            ->assertSessionHas('error');

        $this->assertSame(1, User::where('email', 'awa.jokung@example.com')->count());
    }

    public function test_a_member_cannot_see_or_decide_applications(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $member = User::factory()->create();
        $member->assignRole('member');
        $application = MembershipApplication::create(collect($this->payload())->except(['password_confirmation', 'consent', 'website'])->all() + ['country' => 'France', 'status' => 'pending']);

        $this->actingAs($member)->get(route('membership-applications.index'))->assertForbidden();
        $this->actingAs($member)->patch(route('membership-applications.decide', $application), ['decision' => 'accepted'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'awa.jokung@example.com']);
    }
}
