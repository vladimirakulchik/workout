<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlayerProfile;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerPlayerAssociation>
 */
class TrainerPlayerAssociationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainer_id' => User::factory()->trainer(),
            'player_profile_id' => PlayerProfile::factory(),
            'share_link_id' => null,
            'connected_at' => now(),
            'status' => TrainerPlayerAssociation::STATUS_ACTIVE,
        ];
    }

    public function forTrainer(User $trainer): static
    {
        return $this->state(fn (): array => ['trainer_id' => $trainer->id]);
    }

    public function forProfile(PlayerProfile $profile): static
    {
        return $this->state(fn (): array => ['player_profile_id' => $profile->id]);
    }
}
