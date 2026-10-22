<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ChildPurchaseApproval;
use App\Models\User;

class ChildPurchaseApprovalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlayer();
    }

    /**
     * Only the parent who owns the request decides on it; the child never can.
     */
    public function respond(User $user, ChildPurchaseApproval $approval): bool
    {
        return $user->isPlayer() && $approval->parent_user_id === $user->id;
    }
}
