<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShareLink;
use App\Models\User;

class ShareLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTrainer();
    }

    public function create(User $user): bool
    {
        return $user->isTrainer();
    }

    public function deactivate(User $user, ShareLink $shareLink): bool
    {
        return $user->isTrainer() && $shareLink->trainer_id === $user->id;
    }
}
