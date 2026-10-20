<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Lab404\Impersonate\Events\LeaveImpersonation;

class EndImpersonationLog
{
    public function __construct(private readonly Request $request) {}

    /**
     * Close the log row opened when the impersonation started.
     */
    public function handle(LeaveImpersonation $event): void
    {
        $session = $this->request->session();

        if (! $event->impersonator instanceof User || ! $event->impersonated instanceof User) {
            return;
        }

        ImpersonationLog::query()
            ->whereKey($session->get(StartImpersonationLog::SESSION_LOG_ID))
            ->whereNull('ended_at')
            ->first()
            ?->close();

        $session->forget([StartImpersonationLog::SESSION_LOG_ID, StartImpersonationLog::SESSION_STARTED_AT]);

        activity()
            ->performedOn($event->impersonated)
            ->causedBy($event->impersonator)
            ->event('impersonation_ended')
            ->log('Impersonation ended');
    }
}
