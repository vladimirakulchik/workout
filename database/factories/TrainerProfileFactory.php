<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerProfile>
 */
class TrainerProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->trainer(),
            'business_name' => fake()->company(),
            'address' => fake()->address(),
            'website' => fake()->url(),
            'description' => fake()->paragraph(),
            'logo_path' => null,
            'brand_color' => fake()->hexColor(),
            'stripe_account_id' => null,
            'subscription_status' => null,
            'platform_fee_percent' => null,
        ];
    }
}
