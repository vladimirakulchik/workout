<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\UserStatus;
use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_carry_security_headers(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy');
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get(route('home'))->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    public function test_join_link_is_rate_limited_to_sixty_requests_per_minute(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->get(route('join.show', 'unknown-code'))->assertStatus(404);
        }

        $this->get(route('join.show', 'unknown-code'))->assertStatus(429);
    }

    public function test_every_state_changing_web_route_goes_through_csrf_protection(): void
    {
        $unprotected = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
            ->reject(fn ($route): bool => $route->uri() === 'storage/{path}')
            ->reject(fn ($route): bool => in_array('web', $route->gatherMiddleware(), true))
            ->map(fn ($route): string => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unprotected);
    }

    public function test_deleted_user_is_anonymized_and_cannot_authenticate(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $player = User::factory()->player()->create(['email' => 'real@example.com']);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $player), ['reason' => 'GDPR request'])
            ->assertRedirect();

        $player->refresh();
        $this->assertSame(UserStatus::Deleted, $player->status);
        $this->assertSame('deleted_'.$player->id.'@example.com', $player->email);
        $this->assertDatabaseMissing('users', ['email' => 'real@example.com']);

        auth()->logout();
        $this->post(route('login.store'), ['email' => 'real@example.com', 'password' => 'password'])
            ->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_impersonation_is_logged_and_activity_is_tagged_with_the_real_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trainer = User::factory()->trainer()->create();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $trainer))->assertRedirect();

        $this->assertDatabaseHas('impersonation_logs', [
            'admin_id' => $admin->id,
            'impersonated_id' => $trainer->id,
            'ended_at' => null,
        ]);

        activity()->causedBy($trainer)->log('Acted while impersonated');

        $this->assertSame($admin->id, Activity::query()->latest('id')->first()->getExtraProperty('impersonator_id'));

        $this->delete(route('impersonate.leave'))->assertRedirect();

        $this->assertNotNull(ImpersonationLog::query()->first()->ended_at);
    }
}
