<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\AvailabilityCountRequest;
use App\Http\Requests\Trainer\IndexPlayersRequest;
use App\Models\PlayerProfile;
use App\Models\TrainerPlayerAssociation;
use App\Services\AvailabilitySummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    private const PER_PAGE = 20;

    public function index(IndexPlayersRequest $request, AvailabilitySummary $summary): Response
    {
        $filters = $request->safe()->only(['search', 'day', 'from', 'to']);

        $players = $this->associatedPlayers()
            ->when($filters['search'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes(trim($term), '\\%_').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like);
                });
            })
            ->when($request->hasWindow(), fn (Builder $query) => $query->availableDuring((int) $filters['day'], $filters['from'], $filters['to']))
            ->with('availabilities')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (PlayerProfile $player): array => [
                'id' => $player->id,
                'name' => trim($player->first_name.' '.$player->last_name),
                'age' => $player->age,
                'skill_level' => $player->skill_level,
                'photo_url' => $player->photoUrl(),
                'best_times' => $summary->summarize($player->availabilities),
            ]);

        return Inertia::render('trainer/Players/Index', [
            'players' => $players,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'day' => $request->hasWindow() ? (string) $filters['day'] : '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }

    /**
     * "Available X of Y" for a time window, used when planning sessions.
     */
    public function availabilityCount(AvailabilityCountRequest $request): JsonResponse
    {
        $total = $this->associatedPlayers()->count();
        $available = $this->associatedPlayers()
            ->availableDuring((int) $request->validated('day'), $request->validated('start'), $request->validated('end'))
            ->count();

        return response()->json(['available' => $available, 'total' => $total]);
    }

    /**
     * Player profiles actively connected to the signed-in trainer (the association scope limits it to them).
     *
     * @return Builder<PlayerProfile>
     */
    private function associatedPlayers(): Builder
    {
        return PlayerProfile::query()->whereHas(
            'trainerAssociations',
            fn (Builder $association) => $association->where('status', TrainerPlayerAssociation::STATUS_ACTIVE),
        );
    }
}
