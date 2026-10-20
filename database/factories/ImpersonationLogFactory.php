<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImpersonationLog>
 */
class ImpersonationLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(10, 600));
        $duration = fake()->numberBetween(30, 1800);

        return [
            'admin_id' => User::factory()->superAdmin(),
            'impersonated_id' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->addSeconds($duration),
            'duration_seconds' => $duration,
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function ongoing(): static
    {
        return $this->state(fn (): array => ['ended_at' => null, 'duration_seconds' => null]);
    }
}
