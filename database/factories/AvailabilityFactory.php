<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Availability;
use App\Models\PlayerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Availability>
 */
class AvailabilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'availabilityable_type' => (new PlayerProfile)->getMorphClass(),
            'availabilityable_id' => PlayerProfile::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '17:00:00',
            'end_time' => '20:00:00',
        ];
    }

    public function forOwner(Model $owner): static
    {
        return $this->state(fn (): array => [
            'availabilityable_type' => $owner->getMorphClass(),
            'availabilityable_id' => $owner->getKey(),
        ]);
    }

    public function between(int $day, string $start, string $end): static
    {
        return $this->state(fn (): array => [
            'day_of_week' => $day,
            'start_time' => $start.':00',
            'end_time' => $end.':00',
        ]);
    }
}
