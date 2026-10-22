<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WriteCoachToEvent;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Placeholder until the events epic provides events to attach coaches to.
 */
class NullWriteCoachToEvent implements WriteCoachToEvent
{
    public function handle(int $eventId, User $coach, CarbonInterface $start, CarbonInterface $end): void {}
}
