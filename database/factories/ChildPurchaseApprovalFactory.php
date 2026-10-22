<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentType;
use App\Models\ChildPurchaseApproval;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChildPurchaseApproval>
 */
class ChildPurchaseApprovalFactory extends Factory
{
    /**
     * Pending USD request for a child of a fresh parent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_user_id' => User::factory()->player(),
            'player_profile_id' => fn (array $attributes) => PlayerProfile::factory()->child(User::query()->findOrFail((int) $attributes['parent_user_id'])),
            'trainer_id' => User::factory()->trainer(),
            'subject_type' => null,
            'subject_id' => null,
            'description' => fake()->sentence(3),
            'amount_cents' => fake()->numberBetween(500, 20000),
            'currency' => 'USD',
            'payment_type' => PaymentType::Usd,
            'status' => ApprovalStatus::Pending,
            'requested_at' => now(),
            'responded_at' => null,
            'expires_at' => now()->addHours(ChildPurchaseApproval::EXPIRY_HOURS),
            'parent_notes' => null,
        ];
    }

    public function forChild(PlayerProfile $child): static
    {
        return $this->state(fn (): array => ['player_profile_id' => $child->id, 'parent_user_id' => $child->parent_user_id]);
    }

    public function forTrainer(User $trainer): static
    {
        return $this->state(fn (): array => ['trainer_id' => $trainer->id]);
    }

    public function tokens(): static
    {
        return $this->state(fn (): array => ['payment_type' => PaymentType::Tokens, 'amount_cents' => 30]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => ApprovalStatus::Approved, 'responded_at' => now()]);
    }

    public function denied(): static
    {
        return $this->state(fn (): array => ['status' => ApprovalStatus::Denied, 'responded_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'requested_at' => now()->subHours(49),
            'expires_at' => now()->subHour(),
        ]);
    }
}
