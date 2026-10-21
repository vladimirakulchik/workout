<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Events\ChildRemovedFromTrainer;
use App\Models\PlayerProfile;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;

class RemoveChildFromTrainer
{
    /**
     * Soft delete the association so history is kept, then announce the removal.
     */
    public function handle(PlayerProfile $child, User $trainer): bool
    {
        $association = TrainerPlayerAssociation::query()
            ->withoutGlobalScopes()
            ->where('trainer_id', $trainer->id)
            ->where('player_profile_id', $child->id)
            ->first();

        if ($association === null) {
            return false;
        }

        $association->delete();

        ChildRemovedFromTrainer::dispatch($child, $trainer);

        return true;
    }
}
