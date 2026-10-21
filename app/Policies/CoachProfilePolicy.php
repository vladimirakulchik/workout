<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CoachProfile;
use App\Models\User;

class CoachProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTrainer();
    }

    public function view(User $user, CoachProfile $coachProfile): bool
    {
        return $user->isTrainer() && $coachProfile->trainer_id === $user->id;
    }

    public function remove(User $user, CoachProfile $coachProfile): bool
    {
        return $user->isTrainer() && $coachProfile->trainer_id === $user->id;
    }
}
