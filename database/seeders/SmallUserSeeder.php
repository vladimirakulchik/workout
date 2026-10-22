<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Small demo dataset (40 users) covering every table, for day-to-day development.
 * Run with `php artisan db:seed --class=SmallUserSeeder`.
 */
class SmallUserSeeder extends DemoDataSeeder
{
    protected function userCount(): int
    {
        return 40;
    }

    protected function trainerCount(): int
    {
        return 3;
    }
}
