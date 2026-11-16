<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $country = Country::create(['code' => 'CM', 'name' => 'Cameroun', 'is_active' => true]);
        $user = User::factory()->create(['name' => 'Prems Adjara']);
        MemberProfile::create([
            'user_id' => $user->id,
            'country_id' => $country->id,
            'phone' => '+237670123456',
            'city' => 'Douala',
            'profession' => 'Comptable',
            'date_of_birth' => '1990-05-20',
            'member_since' => '2026-11-09',
            'status' => 'active',
            'notes' => 'Note interne de l\'administration',
        ]);

        return $user;
    }

    public function test_the_profile_page_shows_all_member_information(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('account.name', 'Prems Adjara')
                ->where('profile.phone', '+237670123456')
                ->where('profile.country', 'Cameroun')
                ->where('profile.city', 'Douala')
                ->where('profile.profession', 'Comptable')
                ->where('profile.date_of_birth', '1990-05-20')
                ->where('profile.member_since', '09/11/2026')
                ->where('profile.status_label', 'Actif')
                ->missing('profile.notes') // les notes internes ne sont jamais montrées au membre
                ->has('countries', 1));
    }

    public function test_a_member_can_update_their_own_details(): void
    {
        $user = $this->member();
        $france = Country::create(['code' => 'FR', 'name' => 'France', 'is_active' => true]);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Prems Adjara',
            'email' => $user->email,
            'phone' => '+33612345678',
            'country_id' => $france->id,
            'city' => 'Paris',
            'address' => '10 rue de la Paix',
            'profession' => 'Expert-comptable',
            'date_of_birth' => '1990-05-20',
            'gender' => 'female',
            'bio' => 'Bonjour la famille',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $profile = $user->memberProfile->fresh();
        $this->assertSame('+33612345678', $profile->phone);
        $this->assertSame($france->id, $profile->country_id);
        $this->assertSame('Paris', $profile->city);
        $this->assertSame('female', $profile->gender);
        $this->assertSame('active', $profile->status->value); // le statut ne change pas
    }

    public function test_a_member_cannot_change_status_or_role_from_the_profile(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Prems Adjara',
            'email' => $user->email,
            'status' => 'suspended',
            'member_since' => '2000-01-01',
            'notes' => 'piratage',
        ]);

        $profile = $user->memberProfile->fresh();
        $this->assertSame('active', $profile->status->value);
        $this->assertSame('2026-11-09', $profile->member_since->format('Y-m-d'));
        $this->assertSame('Note interne de l\'administration', $profile->notes);
    }

    public function test_the_phone_must_be_international(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Prems Adjara',
            'email' => $user->email,
            'phone' => '06 12 34 56 78',
        ])->assertSessionHasErrors('phone');
    }
}
