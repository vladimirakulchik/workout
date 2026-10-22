<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\LargeUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsersIndexPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_index_with_ten_thousand_users_is_fast_and_uses_few_queries(): void
    {
        $this->seed(LargeUserSeeder::class);
        $admin = User::factory()->superAdmin()->create();

        $this->assertSame(LargeUserSeeder::TOTAL, User::query()->where('email', '!=', (string) config('app.super_admin_email'))->count() - 1);

        DB::enableQueryLog();
        $startedAt = microtime(true);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'a', 'role' => 'player', 'sort' => 'name']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/Users/Index')->has('users.data'));

        $this->assertLessThan(3.0, microtime(true) - $startedAt);
        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
    }
}
