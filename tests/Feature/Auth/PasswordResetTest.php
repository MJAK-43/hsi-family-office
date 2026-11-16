<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email));

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Nouveau-mot-de-passe-2026',
                'password_confirmation' => 'Nouveau-mot-de-passe-2026',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            // Une confirmation est envoyée, et le lien déjà utilisé n'ouvre plus le formulaire.
            Notification::assertSentTo($user, PasswordChanged::class);
            $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('error');

            return true;
        });
    }

    public function test_an_unknown_or_forged_link_does_not_show_the_form(): void
    {
        $user = User::factory()->create();

        $this->get('/reset-password/faux-jeton?email='.urlencode($user->email))->assertRedirect(route('password.request'));
        $this->get('/reset-password/faux-jeton?email=inconnu@example.com')->assertRedirect(route('password.request'));
    }

    public function test_changing_the_password_from_the_profile_sends_a_confirmation(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'Nouveau-mot-de-passe-2026',
            'password_confirmation' => 'Nouveau-mot-de-passe-2026',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, PasswordChanged::class);
    }
}
