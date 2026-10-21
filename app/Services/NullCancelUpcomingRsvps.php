<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CancelUpcomingRsvps;
use App\Models\PlayerProfile;
use App\Models\User;

/**
 * Placeholder until the events epic provides RSVPs to cancel.
 */
class NullCancelUpcomingRsvps implements CancelUpcomingRsvps
{
    public function handle(PlayerProfile $child, User $trainer): void {}
}
