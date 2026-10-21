<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PlayerProfile;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Works out which (player profile, trainer) pairs a player login may act as, and which one is current.
 *
 * Allowed profiles: the login's own profile(s) ("yours") and the child profiles it is parent of
 * ("children"). A pair is allowed only while the trainer is active and the association is active.
 * The choice is kept in the session as `current_player_id` / `current_trainer_id`.
 *
 * @phpstan-type Option array{player_id: int, trainer_id: int, player_name: string, trainer_name: string, label: string}
 * @phpstan-type Options array{yours: list<Option>, children: list<Option>}
 */
class ContextResolver
{
    public const SESSION_PLAYER = 'current_player_id';

    public const SESSION_TRAINER = 'current_trainer_id';

    /** @var array<int, Options> */
    private array $optionsCache = [];

    /**
     * @return Options
     */
    public function options(User $user): array
    {
        return $this->optionsCache[$user->id] ??= [
            'yours' => $this->optionsFor(
                $user->playerProfiles()->orderBy('id')->get(),
                selfLabel: ! $user->isChildAccount(),
            ),
            'children' => $this->optionsFor(
                $user->childProfiles()->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
                    ->orderBy('id')->get(),
                selfLabel: false,
                childLabel: true,
            ),
        ];
    }

    public function isAllowed(User $user, int $playerId, int $trainerId): bool
    {
        return $this->find($user, $playerId, $trainerId) !== null;
    }

    /**
     * The stored choice if it is still allowed, otherwise the first available option (which is then stored).
     *
     * @return Option|null
     */
    public function resolve(User $user, Session $session): ?array
    {
        $playerId = $session->get(self::SESSION_PLAYER);
        $trainerId = $session->get(self::SESSION_TRAINER);

        if (is_int($playerId) && is_int($trainerId)) {
            $current = $this->find($user, $playerId, $trainerId);

            if ($current !== null) {
                return $current;
            }
        }

        $options = $this->options($user);
        $first = $options['yours'][0] ?? $options['children'][0] ?? null;

        if ($first === null) {
            $session->forget([self::SESSION_PLAYER, self::SESSION_TRAINER]);

            return null;
        }

        $this->store($session, $first['player_id'], $first['trainer_id']);

        return $first;
    }

    /**
     * Store a pair as current. Callers must check `isAllowed()` first.
     */
    public function store(Session $session, int $playerId, int $trainerId): void
    {
        $session->put(self::SESSION_PLAYER, $playerId);
        $session->put(self::SESSION_TRAINER, $trainerId);
    }

    /**
     * @return Option|null
     */
    private function find(User $user, int $playerId, int $trainerId): ?array
    {
        $options = $this->options($user);

        foreach ([...$options['yours'], ...$options['children']] as $option) {
            if ($option['player_id'] === $playerId && $option['trainer_id'] === $trainerId) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @param  iterable<int, PlayerProfile>  $profiles
     * @return list<Option>
     */
    private function optionsFor(iterable $profiles, bool $selfLabel, bool $childLabel = false): array
    {
        $profiles = collect($profiles)->keyBy('id');

        if ($profiles->isEmpty()) {
            return [];
        }

        $associations = TrainerPlayerAssociation::query()
            ->withoutGlobalScope('trainer')
            ->whereIn('player_profile_id', $profiles->keys())
            ->where('status', TrainerPlayerAssociation::STATUS_ACTIVE)
            ->whereHas('trainer', fn ($query) => $query->active())
            ->with('trainer')
            ->orderBy('player_profile_id')
            ->orderBy('connected_at')
            ->orderBy('id')
            ->get();

        $options = [];

        foreach ($associations as $association) {
            $profile = $profiles[$association->player_profile_id];
            $playerName = trim($profile->first_name.' '.$profile->last_name);
            $trainerName = $association->trainer->name;

            $options[] = [
                'player_id' => $profile->id,
                'trainer_id' => $association->trainer_id,
                'player_name' => $playerName,
                'trainer_name' => $trainerName,
                'label' => match (true) {
                    $childLabel => $playerName.' → '.$trainerName,
                    $selfLabel => 'Me → '.$trainerName,
                    default => $trainerName,
                },
            ];
        }

        return $options;
    }
}
