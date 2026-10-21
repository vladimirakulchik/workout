<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\PlayerProfile;
use App\Models\User;

interface CancelUpcomingRsvps
{
    /**
     * Cancel every upcoming RSVP the player profile holds with the trainer.
     */
    public function handle(PlayerProfile $child, User $trainer): void;
}
