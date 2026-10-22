<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachAvailabilityOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachAvailabilityOverride>
 */
class CoachAvailabilityOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => fake()->numberBetween(1, 10000),
            'coach_id' => User::factory()->coach(),
            'overridden_by' => User::factory()->trainer(),
            'reason' => fake()->sentence(),
        ];
    }
}
