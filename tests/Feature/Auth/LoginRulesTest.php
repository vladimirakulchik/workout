<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_users_cannot_log_in_and_see_deactivation_message(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Account deactivated. Contact support.']);

        $this->assertGuest();
    }

    public function test_deleted_users_get_generic_invalid_credentials_error(): void
    {
        $user = User::factory()->deleted()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_inactive_status_is_not_revealed_with_wrong_password(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->last_login_at);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertTooManyRequests();

        $this->assertGuest();

        RateLimiter::clear(md5('login'.implode('|', [$user->email, '127.0.0.1'])));
    }

    public function test_remember_me_sets_the_recaller_cookie(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertCookie(auth()->guard()->getRecallerName());
    }

    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    #[DataProvider('roleRedirects')]
    public function test_users_are_redirected_after_login_by_role(string $role, string $routeName): void
    {
        $user = User::factory()->{$role}()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route($routeName));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function roleRedirects(): array
    {
        return [
            'super admin' => ['superAdmin', 'admin.dashboard'],
            'trainer' => ['trainer', 'trainer.dashboard'],
            'coach' => ['coach', 'coach.dashboard'],
            'player' => ['player', 'player.dashboard'],
        ];
    }

    public function test_users_deactivated_mid_session_are_logged_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('player.dashboard'))->assertOk();

        $user->forceFill(['status' => 'inactive'])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_page_has_no_registration_link_and_register_route_is_gone(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
