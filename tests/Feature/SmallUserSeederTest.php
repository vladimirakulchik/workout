<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\SmallUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmallUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_application_table_receives_data(): void
    {
        $this->seed(SmallUserSeeder::class);

        $tables = [
            'users', 'trainer_profiles', 'coach_profiles', 'player_profiles', 'activity_log', 'user_deletion_logs',
            'impersonation_logs', 'share_links', 'trainer_player_associations', 'availabilities',
            'coach_availability_overrides', 'notifications', 'child_purchase_approvals',
        ];

        foreach ($tables as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), "Table {$table} is empty.");
        }

        $this->assertSame(40, DB::table('users')->where('email', '!=', (string) config('app.super_admin_email'))->count());
    }

    public function test_players_with_a_login_share_their_name_with_their_profile(): void
    {
        $this->seed(SmallUserSeeder::class);

        $mismatches = DB::table('player_profiles')
            ->join('users', 'users.id', '=', 'player_profiles.user_id')
            ->where(fn ($query) => $query
                ->whereColumn('users.first_name', '!=', 'player_profiles.first_name')
                ->orWhereColumn('users.last_name', '!=', 'player_profiles.last_name'))
            ->count();

        $this->assertSame(0, $mismatches);
    }
}
