<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachProfile>
 */
class CoachProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->coach(),
            'trainer_id' => null,
            'bio' => fake()->paragraph(),
            'credentials' => fake()->sentence(),
            'certifications' => fake()->sentence(),
            'is_public' => false,
            'joined_at' => null,
        ];
    }

    public function forTrainer(User $trainer): static
    {
        return $this->state(fn (): array => ['trainer_id' => $trainer->id, 'joined_at' => now()]);
    }

    public function public(): static
    {
        return $this->state(fn (): array => ['is_public' => true]);
    }
}
