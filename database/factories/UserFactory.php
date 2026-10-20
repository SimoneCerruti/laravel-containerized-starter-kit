<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory {
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<model-property<User>, mixed>
     */
    public function definition(): array {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // @kit:factory-definition
        ];
    }

    public function verified(): static {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => now(),
        ]);
    }

    public function unverified(): static {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function withPassword(string $password): static {
        return $this->state(fn (array $attributes): array => [
            'password' => $password,
        ]);
    }

    public function withoutRememberToken(): static {
        return $this->state(fn (array $attributes): array => [
            'remember_token' => null,
        ]);
    }

    // @kit:factory-states
}
