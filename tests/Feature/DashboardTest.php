<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CoachProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function roles(): array
    {
        return [
            'super admin' => ['superAdmin', 'admin.dashboard', 'admin/Dashboard'],
            'trainer' => ['trainer', 'trainer.dashboard', 'trainer/Dashboard'],
            'coach' => ['coach', 'coach.dashboard', 'coach/Dashboard'],
            'player' => ['player', 'player.dashboard', 'player/Dashboard'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function roleRoutes(): array
    {
        return array_map(fn (array $role): array => [$role[0], $role[1]], self::roles());
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        foreach (self::roles() as [, $routeName]) {
            $this->get(route($routeName))->assertRedirect(route('login'));
        }
    }

    #[DataProvider('roleRoutes')]
    public function test_dashboard_route_redirects_to_the_role_dashboard(string $state, string $routeName): void
    {
        $user = User::factory()->{$state}()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($routeName));
    }

    #[DataProvider('roles')]
    public function test_each_role_reaches_its_own_dashboard(string $state, string $routeName, string $component): void
    {
        $user = User::factory()->{$state}()->create();

        $this->actingAs($user)->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->has('stats')
                ->where('auth.user.role', $user->role->value)
                ->where('auth.user.impersonating', false)
                ->has('navigation')
            );
    }

    #[DataProvider('roleRoutes')]
    public function test_roles_get_403_on_other_role_dashboards(string $state, string $ownRoute): void
    {
        $user = User::factory()->{$state}()->create();

        foreach (self::roles() as [, $routeName]) {
            if ($routeName !== $ownRoute) {
                $this->actingAs($user)->get(route($routeName))->assertForbidden();
            }
        }
    }

    public function test_navigation_only_contains_registered_routes_for_the_role(): void
    {
        $trainer = User::factory()->trainer()->create();

        $this->actingAs($trainer)->get(route('trainer.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('navigation', 2)
                ->where('navigation.0.title', 'Dashboard')
                ->where('navigation.0.href', route('trainer.dashboard', absolute: false))
                ->where('navigation.1.title', 'Share Links')
                ->where('navigation.1.href', route('trainer.share-links.index', absolute: false))
            );

        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->get(route('coach.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('navigation', 2)
                ->where('navigation.1.title', 'Profile')
            );
    }

    public function test_trainer_dashboard_counts_only_own_coaches(): void
    {
        $trainer = User::factory()->trainer()->create();
        CoachProfile::factory()->forTrainer($trainer)->create();
        CoachProfile::factory()->forTrainer(User::factory()->trainer()->create())->create();

        $this->actingAs($trainer)->get(route('trainer.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.0.value', 1));
    }

    public function test_shared_auth_user_does_not_expose_sensitive_fields(): void
    {
        $user = User::factory()->player()->create();

        $this->actingAs($user)->get(route('player.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->where('branding', null)
            );
    }
}
