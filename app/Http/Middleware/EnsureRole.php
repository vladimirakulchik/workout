<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Abort with 403 unless the authenticated user has one of the given roles.
     *
     * Usage: `role:trainer` or `role:trainer,coach` (UserRole values).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(
            static fn (string $role): ?UserRole => UserRole::tryFrom($role),
            $roles,
        );

        abort_unless(in_array($request->user()?->role, $allowed, true), 403);

        return $next($request);
    }
}
