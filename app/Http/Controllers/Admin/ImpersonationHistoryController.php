<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationLog;
use Inertia\Inertia;
use Inertia\Response;

class ImpersonationHistoryController extends Controller
{
    private const PER_PAGE = 25;

    public function __invoke(): Response
    {
        $logs = ImpersonationLog::query()
            ->with(['admin:id,first_name,last_name,status', 'impersonated:id,first_name,last_name,role,status'])
            ->latest('started_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (ImpersonationLog $log): array => [
                'id' => $log->id,
                'admin' => $log->admin?->displayName(),
                'user' => $log->impersonated?->displayName(),
                'user_role' => $log->impersonated?->role->label(),
                'started_at' => $log->started_at->toIso8601String(),
                'ended_at' => $log->ended_at?->toIso8601String(),
                'duration_seconds' => $log->duration_seconds,
            ]);

        return Inertia::render('admin/ImpersonationHistory', ['logs' => $logs]);
    }
}
