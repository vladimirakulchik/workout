<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Listeners\StartImpersonationLog;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;

class ImpersonationTimeout
{
    public const MAX_SECONDS = 3600;

    public function __construct(private readonly ImpersonateManager $impersonation) {}

    /**
     * End an impersonation session that has been running for more than an hour.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonation->isImpersonating()) {
            return $next($request);
        }

        $startedAt = (int) $request->session()->get(StartImpersonationLog::SESSION_STARTED_AT, 0);

        if (now()->getTimestamp() - $startedAt <= self::MAX_SECONDS || $request->routeIs('impersonate.leave')) {
            return $next($request);
        }

        $this->impersonation->leave();

        Inertia::flash('toast', ['type' => 'error', 'message' => __('Impersonation expired after 1 hour.')]);

        return redirect()->route('admin.users.index');
    }
}
