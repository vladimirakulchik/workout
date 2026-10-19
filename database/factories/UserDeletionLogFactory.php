<?php

namespace Database\Factories;

use App\Models\UserDeletionLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDeletionLog>
 */
class UserDeletionLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_user_id' => fake()->unique()->numberBetween(1000, 99999),
            'original_email' => fake()->safeEmail(),
            'deleted_by' => null,
            'reason' => fake()->sentence(),
            'original_data' => ['user' => ['first_name' => fake()->firstName()]],
            'deleted_at' => now(),
        ];
    }
}
