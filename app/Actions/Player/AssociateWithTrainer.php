<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\TrainerPlayerAssociation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssociateWithTrainer
{
    /**
     * Connect the given profiles to the link's trainer. Profiles that are already connected are skipped;
     * a previously removed association is restored. The link's use counter grows once per call that
     * connected at least one new profile.
     *
     * @param  Collection<int, PlayerProfile>  $profiles
     * @return Collection<int, PlayerProfile> The profiles that were newly connected.
     */
    public function handle(ShareLink $link, Collection $profiles): Collection
    {
        return DB::transaction(function () use ($link, $profiles): Collection {
            $created = $profiles->filter(function (PlayerProfile $profile) use ($link): bool {
                $association = TrainerPlayerAssociation::query()
                    ->withoutGlobalScopes()
                    ->withTrashed()
                    ->where('trainer_id', $link->trainer_id)
                    ->where('player_profile_id', $profile->id)
                    ->first();

                if ($association !== null && ! $association->trashed()) {
                    return false;
                }

                $attributes = [
                    'share_link_id' => $link->id,
                    'connected_at' => now(),
                    'status' => TrainerPlayerAssociation::STATUS_ACTIVE,
                ];

                if ($association === null) {
                    TrainerPlayerAssociation::query()->withoutGlobalScopes()->create([
                        'trainer_id' => $link->trainer_id,
                        'player_profile_id' => $profile->id,
                        ...$attributes,
                    ]);
                } else {
                    $association->fill($attributes);
                    $association->restore();
                    $association->save();
                }

                return true;
            })->values();

            if ($created->isNotEmpty()) {
                $link->increment('uses_count');
            }

            return $created;
        });
    }
}
