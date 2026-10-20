<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Lab404\Impersonate\Events\TakeImpersonation;

class StartImpersonationLog
{
    public const SESSION_LOG_ID = 'impersonation_log_id';

    public const SESSION_STARTED_AT = 'impersonation_started_at';

    public function __construct(private readonly Request $request) {}

    /**
     * Record the start of an impersonation and remember it in the session for the timeout and leave steps.
     */
    public function handle(TakeImpersonation $event): void
    {
        if (! $event->impersonator instanceof User || ! $event->impersonated instanceof User) {
            return;
        }

        $log = ImpersonationLog::create([
            'admin_id' => $event->impersonator->getAuthIdentifier(),
            'impersonated_id' => $event->impersonated->getAuthIdentifier(),
            'started_at' => now(),
            'ip_address' => (string) $this->request->ip(),
        ]);

        $this->request->session()->put([
            self::SESSION_LOG_ID => $log->id,
            self::SESSION_STARTED_AT => $log->started_at->getTimestamp(),
        ]);

        activity()
            ->performedOn($event->impersonated)
            ->causedBy($event->impersonator)
            ->event('impersonation_started')
            ->log('Impersonation started');
    }
}
