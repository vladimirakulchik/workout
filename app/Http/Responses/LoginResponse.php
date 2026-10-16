<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Redirect the authenticated user to the dashboard of their role.
     */
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        return redirect()->intended(self::homeUrl($request->user()));
    }

    /**
     * Resolve the home URL for a user's role.
     *
     * Role dashboards are created in TASK-005; until then they fall back to the generic dashboard.
     */
    public static function homeUrl(User $user): string
    {
        $routeName = match ($user->role) {
            UserRole::SuperAdmin => 'admin.dashboard',
            UserRole::Trainer => 'trainer.dashboard',
            UserRole::Coach => 'coach.dashboard',
            UserRole::Player => 'player.dashboard',
        };

        return route(Route::has($routeName) ? $routeName : 'dashboard');
    }
}
