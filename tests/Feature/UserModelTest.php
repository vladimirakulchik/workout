<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_defaults_to_active_verified_player(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Player, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->must_change_password);
    }

    public function test_factory_states(): void
    {
        $this->assertTrue(User::factory()->superAdmin()->create()->isSuperAdmin());
        $this->assertTrue(User::factory()->trainer()->create()->isTrainer());
        $this->assertTrue(User::factory()->coach()->create()->isCoach());
        $this->assertTrue(User::factory()->player()->create()->isPlayer());
        $this->assertSame(UserStatus::Inactive, User::factory()->inactive()->create()->status);
        $this->assertSame(UserStatus::Deleted, User::factory()->deleted()->create()->status);
        $this->assertNull(User::factory()->unverified()->create()->email_verified_at);
    }

    public function test_name_accessor_joins_first_and_last_name(): void
    {
        $user = User::factory()->make(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('Ada Lovelace', $user->toArray()['name']);
    }

    public function test_scopes_filter_by_role_and_status(): void
    {
        $trainer = User::factory()->trainer()->create();
        User::factory()->coach()->create();
        User::factory()->trainer()->inactive()->create();

        $this->assertEqualsCanonicalizing(
            [$trainer->id],
            User::query()->ofRole(UserRole::Trainer)->active()->pluck('id')->all(),
        );
    }

    public function test_search_matches_name_email_and_phone(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Zelda',
            'last_name' => 'Quimby',
            'email' => 'zq@example.com',
            'phone' => '+15550001',
        ]);
        User::factory()->create(['first_name' => 'Other', 'last_name' => 'Person']);

        foreach (['Zeld', 'quimb', 'zq@', '5550001'] as $term) {
            $this->assertSame([$user->id], User::search($term)->pluck('id')->all(), $term);
        }
    }

    public function test_search_treats_input_as_literal_and_ignores_blank_terms(): void
    {
        User::factory()->count(2)->create();

        $this->assertCount(0, User::search("%' OR 1=1 --")->get());
        $this->assertCount(0, User::search('%')->get());
        $this->assertCount(2, User::search('  ')->get());
        $this->assertCount(2, User::search(null)->get());
    }

    public function test_only_non_super_admins_can_be_impersonated(): void
    {
        $this->assertFalse(User::factory()->superAdmin()->make()->canBeImpersonated());

        foreach (['trainer', 'coach', 'player'] as $state) {
            $this->assertTrue(User::factory()->{$state}()->make()->canBeImpersonated());
        }
    }

    public function test_user_without_player_profile_is_not_a_child_account(): void
    {
        $this->assertFalse(User::factory()->player()->create()->isChildAccount());
    }

    public function test_seeder_creates_verified_super_admin_from_config(): void
    {
        config([
            'app.super_admin_email' => 'root@example.com',
            'app.super_admin_password' => 'a-strong-password',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'root@example.com')->firstOrFail();
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertNotNull($admin->email_verified_at);
        $this->assertSame(1, User::ofRole(UserRole::SuperAdmin)->count());
    }

    public function test_seeder_skips_super_admin_without_credentials_and_demo_data_outside_local(): void
    {
        config(['app.super_admin_email' => null, 'app.super_admin_password' => null]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
    }
}
