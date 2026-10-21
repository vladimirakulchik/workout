<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Actions\Availability\SyncAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\AvailabilityGridRequest;
use App\Models\Availability;
use App\Models\PlayerProfile;
use App\Support\CurrentContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityController extends Controller
{
    public function edit(Request $request, CurrentContext $context): Response
    {
        $profiles = $this->manageableProfiles($request);

        $requestedId = $request->integer('profile') ?: $context->playerId();
        $profile = $profiles->firstWhere('id', $requestedId) ?? $profiles->first();

        return Inertia::render('player/Availability', [
            'profile' => $profile === null ? null : [
                'id' => $profile->id,
                'name' => trim($profile->first_name.' '.$profile->last_name),
            ],
            'profiles' => $profiles->map(fn (PlayerProfile $candidate): array => [
                'id' => $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
            ])->values()->all(),
            'ranges' => $profile?->availabilities->map(fn (Availability $range): array => [
                'day_of_week' => $range->day_of_week,
                'start_time' => $range->startHour(),
                'end_time' => $range->endHour(),
            ])->all() ?? [],
        ]);
    }

    public function update(AvailabilityGridRequest $request, SyncAvailability $syncAvailability): RedirectResponse
    {
        $profile = PlayerProfile::query()->findOrFail($request->integer('profile_id'));

        Gate::authorize('manageAvailability', $profile);

        $syncAvailability->handle($profile, $request->ranges());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Availability saved. Trainers can see these preferences when planning sessions.'),
        ]);

        return redirect()->route('player.availability.edit', ['profile' => $profile->id]);
    }

    /**
     * Own profile(s) plus the children this login is parent of.
     *
     * @return Collection<int, PlayerProfile>
     */
    private function manageableProfiles(Request $request): Collection
    {
        $userId = $request->user()->id;

        return PlayerProfile::query()
            ->where(fn ($query) => $query->where('user_id', $userId)->orWhere('parent_user_id', $userId))
            ->with('availabilities')
            ->orderByRaw('case when user_id = ? then 0 else 1 end', [$userId])
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }
}
