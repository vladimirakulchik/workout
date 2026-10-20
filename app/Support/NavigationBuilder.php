<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class NavigationBuilder
{
    /**
     * Sidebar items per role as [title, route name, lucide icon name].
     * Items whose route is not registered yet are omitted.
     *
     * @var array<string, list<array{0: string, 1: string, 2: string}>>
     */
    private const ITEMS = [
        'super_admin' => [
            ['Dashboard', 'admin.dashboard', 'LayoutGrid'],
            ['Users', 'admin.users.index', 'Users'],
            ['Impersonation History', 'admin.impersonation-history', 'History'],
        ],
        'trainer' => [
            ['Dashboard', 'trainer.dashboard', 'LayoutGrid'],
            ['Players', 'trainer.players.index', 'Users'],
            ['Coaches', 'trainer.coaches.index', 'ClipboardList'],
            ['Share Links', 'trainer.share-links.index', 'Link'],
            ['Branding', 'trainer.branding.edit', 'Palette'],
        ],
        'coach' => [
            ['Dashboard', 'coach.dashboard', 'LayoutGrid'],
            ['My Times', 'coach.availability.edit', 'Clock'],
            ['Profile', 'profile.edit', 'UserRound'],
        ],
        'player' => [
            ['Dashboard', 'player.dashboard', 'LayoutGrid'],
            ['Players / Family', 'player.family.index', 'Users'],
            ['Best Times', 'player.availability.edit', 'Clock'],
            ['Approvals', 'player.approvals.index', 'ClipboardCheck'],
            ['Profile', 'profile.edit', 'UserRound'],
        ],
    ];

    /**
     * @return list<array{title: string, href: string, icon: string}>
     */
    public function for(User $user): array
    {
        $items = [];

        foreach (self::ITEMS[$user->role->value] as [$title, $routeName, $icon]) {
            if (Route::has($routeName)) {
                $items[] = ['title' => $title, 'href' => route($routeName, absolute: false), 'icon' => $icon];
            }
        }

        return $items;
    }

    /**
     * Named route of the dashboard for a role.
     */
    public static function dashboardRoute(UserRole $role): string
    {
        return match ($role) {
            UserRole::SuperAdmin => 'admin.dashboard',
            UserRole::Trainer => 'trainer.dashboard',
            UserRole::Coach => 'coach.dashboard',
            UserRole::Player => 'player.dashboard',
        };
    }
}
