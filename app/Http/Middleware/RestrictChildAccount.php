<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RestrictChildAccount
{
    /**
     * Abort with 403 when the signed-in login is a child account (see the `child.restricted` gate).
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Gate::allows('child.restricted'), 403, __('This action is not available for child accounts.'));

        return $next($request);
    }
}
