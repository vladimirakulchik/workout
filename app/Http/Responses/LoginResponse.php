<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use App\Support\NavigationBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
     */
    public static function homeUrl(User $user): string
    {
        return route(NavigationBuilder::dashboardRoute($user->role));
    }
}
