<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePhotoAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_upload_and_remove_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->image('moi.jpg', 800, 600)])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertStringContainsString('/storage/avatars/', $user->avatarUrl());

        // La photo est visible partout via les données partagées.
        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.avatar_url', $user->avatarUrl()));

        $old = $user->avatar_path;
        $this->actingAs($user)->delete('/profile/photo')->assertSessionHas('success');
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_replacing_the_photo_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('a.png', 400, 400)]);
        $first = $user->fresh()->avatar_path;
        $this->actingAs($user)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('b.jpg', 400, 400)]);

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
    }

    public function test_invalid_photos_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/photo', ['photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->actingAs($user)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('petite.jpg', 50, 50)])
            ->assertSessionHasErrors('photo');
        $this->actingAs($user)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('enorme.jpg', 800, 800)->size(6000)])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_the_language_can_be_switched_for_guests_and_members(): void
    {
        // Navigateur réglé en français : l'interface s'affiche en français.
        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9']);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));

        // Le choix explicite (sélecteur FR / EN) l'emporte sur la langue du navigateur.
        $this->from('/login')->post('/locale', ['locale' => 'en'])->assertRedirect('/login');
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

        $user = User::factory()->create();
        $this->actingAs($user)->post('/locale', ['locale' => 'en']);
        $this->assertSame('en', $user->fresh()->locale);

        $this->post('/locale', ['locale' => 'de'])->assertSessionHasErrors('locale');
    }

    #[DataProvider('countryLocales')]
    public function test_the_default_language_depends_on_the_visitor_country(?string $country, string $browser, string $expected): void
    {
        $headers = ['Accept-Language' => $browser];
        if ($country) {
            $headers['CF-IPCountry'] = $country;
        }

        $this->withHeaders($headers)
            ->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('locale', $expected));
    }

    public static function countryLocales(): array
    {
        return [
            'États-Unis' => ['US', 'fr-FR,fr;q=0.9', 'en'],
            'Royaume-Uni' => ['GB', 'en-GB', 'en'],
            'Nigeria' => ['NG', 'en-US', 'en'],
            'France' => ['FR', 'en-US,en;q=0.9', 'fr'],
            'Belgique' => ['BE', 'fr-BE', 'fr'],
            'Cameroun, navigateur français' => ['CM', 'fr-FR', 'fr'],
            'Cameroun, navigateur anglais' => ['CM', 'en-GB,en;q=0.9', 'en'],
            'Canada, navigateur français' => ['CA', 'fr-CA,fr;q=0.9', 'fr'],
            'Canada, autre langue' => ['CA', 'es-ES', 'en'],
            'Pays inconnu' => [null, 'en-US,en;q=0.9', 'fr'],
        ];
    }

    public function test_the_country_is_found_from_the_ip_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->withHeaders(['Accept-Language' => 'fr-FR'])
            ->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_an_explicit_choice_beats_the_country(): void
    {
        $this->withHeaders(['CF-IPCountry' => 'US'])->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

        $this->post('/locale', ['locale' => 'fr']);

        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));
    }

    public function test_emails_follow_the_member_language(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $this->assertSame('en', $user->preferredLocale());

        app()->setLocale('en');
        $this->assertSame('Reset your password — HSI', __('Réinitialisation de votre mot de passe — HSI'));
        $this->assertSame('Your profile has been updated.', __('Votre profil a été mis à jour.'));
    }
}
