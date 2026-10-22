<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;
use Carbon\CarbonInterface;

interface WriteCoachToEvent
{
    /**
     * Attach the coach to the event for the given time window.
     */
    public function handle(int $eventId, User $coach, CarbonInterface $start, CarbonInterface $end): void;
}
