<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: the Super Admin everywhere, plus the small demo dataset locally.
     * Use `--class=LargeUserSeeder` for the 10,000 user performance dataset.
     */
    public function run(): void
    {
        $this->call(app()->environment('local') ? SmallUserSeeder::class : SuperAdminSeeder::class);
    }
}
