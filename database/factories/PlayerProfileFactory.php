<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerProfile>
 */
class PlayerProfileFactory extends Factory
{
    /**
     * Defaults to a self profile with its own login.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->player(),
            'parent_user_id' => null,
            'first_name' => fn (array $attributes): string => $this->loginName($attributes, 'first_name', fake()->firstName()),
            'last_name' => fn (array $attributes): string => $this->loginName($attributes, 'last_name', fake()->lastName()),
            'birth_date' => fake()->dateTimeBetween('-40 years', '-19 years'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'skill_level' => null,
            'school' => null,
            'jersey_number' => null,
            'photo_path' => null,
            'is_child' => false,
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'allow_token_spending' => false,
        ];
    }

    /**
     * A profile with a login always shares the login's name; a child without a login gets its own.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function loginName(array $attributes, string $column, string $fallback): string
    {
        $userId = $attributes['user_id'] ?? null;

        if ($userId === null) {
            return $fallback;
        }

        return (string) User::query()->findOrFail((int) $userId)->{$column};
    }

    /**
     * Profile owned by the given user (no parent).
     */
    public function self(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user ?? User::factory()->player(),
            'parent_user_id' => null,
            'is_child' => false,
        ]);
    }

    /**
     * Child profile owned by a parent, without a login unless user_id is overridden.
     */
    public function child(?User $parent = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'parent_user_id' => $parent ?? User::factory()->player(),
            'is_child' => true,
            'birth_date' => fake()->dateTimeBetween('-17 years', '-6 years'),
        ]);
    }
}
