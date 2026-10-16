<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedSuperAdmin();

        if (app()->environment('local')) {
            $this->seedDemoUsers();
        }
    }

    /**
     * Create the verified Super Admin from configuration; skipped when credentials are not set.
     */
    private function seedSuperAdmin(): void
    {
        $email = config('app.super_admin_email');
        $password = config('app.super_admin_password');

        if (! $email || ! $password) {
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedDemoUsers(): void
    {
        User::factory()->trainer()->count(2)->create();
        User::factory()->coach()->count(3)->create();
        User::factory()->player()->count(5)->create();
    }
}
