<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ImpersonationTimeout;
use App\Listeners\StartImpersonationLog;
use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_impersonate_and_is_redirected_to_role_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainer = User::factory()->trainer()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $trainer))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($trainer);
        $this->get(route('dashboard'))->assertRedirect(route('trainer.dashboard'));
    }

    public function test_cannot_impersonate_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $other = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $other))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.impersonate', $admin))->assertForbidden();

        $this->assertDatabaseCount('impersonation_logs', 0);
    }

    public function test_cannot_impersonate_inactive_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $inactive = User::factory()->player()->inactive()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $inactive))->assertForbidden();

        $this->assertDatabaseCount('impersonation_logs', 0);
    }

    public function test_non_admin_cannot_impersonate(): void
    {
        $trainer = User::factory()->trainer()->create();
        $player = User::factory()->player()->create();

        $this->actingAs($trainer)->post(route('admin.users.impersonate', $player))->assertForbidden();
    }

    public function test_log_is_created_and_closed_with_duration(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $player));

        $log = ImpersonationLog::sole();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame($player->id, $log->impersonated_id);
        $this->assertNull($log->ended_at);
        $this->assertNotEmpty($log->ip_address);

        $this->travel(95)->seconds();

        $this->delete(route('impersonate.leave'))->assertRedirect(route('admin.users.index'));

        $log->refresh();
        $this->assertNotNull($log->ended_at);
        $this->assertSame(95, $log->duration_seconds);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_impersonated_session_can_reach_leave_route_without_admin_role(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $player));

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->delete(route('impersonate.leave'))->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_leave_without_impersonating_does_nothing(): void
    {
        $player = User::factory()->player()->create();

        $this->actingAs($player)->delete(route('impersonate.leave'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($player);
    }

    public function test_impersonation_expires_after_one_hour(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $player));

        $this->travel(ImpersonationTimeout::MAX_SECONDS - 60)->seconds();
        $this->get(route('player.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($player);

        $this->travel(120)->seconds();
        $this->get(route('player.dashboard'))->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull(ImpersonationLog::sole()->ended_at);
        $this->assertFalse(session()->has(StartImpersonationLog::SESSION_LOG_ID));
    }

    public function test_banner_prop_is_shared_only_while_impersonating(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->create(['first_name' => 'Pat', 'last_name' => 'Player']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.impersonating', null));

        $this->post(route('admin.users.impersonate', $player));

        $this->get(route('player.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.impersonating.name', 'Pat Player')
                ->where('auth.impersonating.role_label', 'Player')
                ->where('auth.user.id', $player->id));
    }

    public function test_permissions_match_the_impersonated_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainer = User::factory()->trainer()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $trainer));

        $this->get(route('trainer.dashboard'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('player.dashboard'))->assertForbidden();
        $this->post(route('admin.users.impersonate', User::factory()->player()->create()))->assertForbidden();
    }

    public function test_activity_entries_carry_impersonator_id_only_while_impersonating(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->create();

        activity()->log('before');
        $this->assertNull(Activity::where('description', 'before')->sole()->getExtraProperty('impersonator_id'));

        $this->actingAs($admin)->post(route('admin.users.impersonate', $player));
        activity()->causedBy($player)->log('during');

        $this->assertSame($admin->id, Activity::where('description', 'during')->sole()->getExtraProperty('impersonator_id'));
    }

    public function test_history_page_lists_logs_for_super_admin_only(): void
    {
        $admin = User::factory()->superAdmin()->create();
        ImpersonationLog::factory()->count(3)->create();

        $this->actingAs($admin)->get(route('admin.impersonation-history'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/ImpersonationHistory')
                ->has('logs.data', 3)
                ->has('logs.data.0', fn (Assert $row) => $row
                    ->hasAll(['id', 'admin', 'user', 'user_role', 'started_at', 'ended_at', 'duration_seconds'])));

        $this->actingAs(User::factory()->trainer()->create())
            ->get(route('admin.impersonation-history'))
            ->assertForbidden();
    }
}
