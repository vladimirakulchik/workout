<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Player,
            'status' => UserStatus::Active,
            'phone' => null,
            'photo_path' => null,
            'must_change_password' => false,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::SuperAdmin]);
    }

    public function trainer(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Trainer]);
    }

    public function coach(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Coach]);
    }

    public function player(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Player]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => UserStatus::Inactive]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => UserStatus::Deleted]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
