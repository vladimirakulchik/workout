<?php

declare(strict_types=1);

namespace App\Actions\Trainer;

use App\Models\CoachProfile;

class RemoveCoach
{
    /**
     * Detach the coach from the trainer. The profile and its joined_at history are kept.
     */
    public function handle(CoachProfile $coachProfile): void
    {
        $coachProfile->forceFill(['trainer_id' => null])->save();
    }
}
