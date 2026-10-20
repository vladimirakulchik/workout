<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShareLinkType;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShareLink>
 */
class ShareLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::random(10),
            'type' => ShareLinkType::Player,
            'trainer_id' => User::factory()->trainer(),
            'created_by' => fn (array $attributes) => $attributes['trainer_id'],
            'target_email' => null,
            'name' => null,
            'message' => null,
            'expires_at' => null,
            'max_uses' => null,
            'uses_count' => 0,
            'is_active' => true,
        ];
    }

    public function forTrainer(User $trainer): static
    {
        return $this->state(fn (): array => ['trainer_id' => $trainer->id, 'created_by' => $trainer->id]);
    }

    public function coach(): static
    {
        return $this->state(fn (): array => [
            'type' => ShareLinkType::Coach,
            'target_email' => fake()->safeEmail(),
            'expires_at' => now()->addDays(7),
            'max_uses' => 1,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_uses' => $attributes['max_uses'] ?? 1,
            'uses_count' => $attributes['max_uses'] ?? 1,
        ]);
    }
}
