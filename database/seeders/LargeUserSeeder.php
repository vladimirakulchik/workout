<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Performance dataset (10,000 users) covering every table, to verify list pages stay fast.
 * Run with `php artisan db:seed --class=LargeUserSeeder`.
 */
class LargeUserSeeder extends DemoDataSeeder
{
    public const TOTAL = 10000;

    protected function userCount(): int
    {
        return self::TOTAL;
    }

    protected function trainerCount(): int
    {
        return 20;
    }

    protected function approvalCount(): int
    {
        return 500;
    }

    protected function logsUserCreation(): bool
    {
        return false;
    }
}
