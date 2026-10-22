<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create the verified Super Admin from configuration; skipped when credentials are not set.
     */
    public function run(): void
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
}
