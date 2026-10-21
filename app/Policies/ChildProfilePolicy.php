<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlayerProfile;
use App\Models\User;

class ChildProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlayer() && ! $user->isChildAccount();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PlayerProfile $profile): bool
    {
        return $profile->is_child && $profile->parent_user_id === $user->id;
    }

    /**
     * Own profile, or a child profile the user is parent of.
     */
    public function manageAvailability(User $user, PlayerProfile $profile): bool
    {
        return $user->isPlayer() && ($profile->user_id === $user->id || $profile->parent_user_id === $user->id);
    }

    public function delete(User $user, PlayerProfile $profile): bool
    {
        return $this->update($user, $profile);
    }
}
