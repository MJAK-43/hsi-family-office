<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Par défaut le règlement intérieur est déjà accepté ; voir unacceptedPolicy().
            'policy_accepted_at' => now(),
            'policy_accepted_version' => \App\Support\FundRules::get('policy_version'),
        ];
    }

    /** Utilisateur qui n'a pas encore accepté le règlement intérieur. */
    public function unacceptedPolicy(): static
    {
        return $this->state(fn (array $attributes) => [
            'policy_accepted_at' => null,
            'policy_accepted_version' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
