<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
final class SocialAccountFactory extends Factory {
    /**
     * @return array<model-property<SocialAccount>, mixed>
     */
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['google', 'github']),
            'provider_user_id' => (string) fake()->unique()->numberBetween(1_000_000, 999_999_999),
            'token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ];
    }

    public function forProvider(string $provider): static {
        return $this->state(fn (array $attributes): array => [
            'provider' => $provider,
        ]);
    }

    public function withTokens(): static {
        return $this->state(fn (array $attributes): array => [
            'token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'expires_at' => now()->addHour(),
        ]);
    }
}
