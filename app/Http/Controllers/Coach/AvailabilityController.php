<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coach;

use App\Actions\Availability\SyncAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\AvailabilityGridRequest;
use App\Models\Availability;
use App\Models\CoachProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityController extends Controller
{
    public function edit(Request $request): Response
    {
        $profile = $this->profile($request);

        return Inertia::render('coach/MyTimes', [
            'isPublic' => $profile->is_public,
            'ranges' => $profile->availabilities->map(fn (Availability $range): array => [
                'day_of_week' => $range->day_of_week,
                'start_time' => $range->startHour(),
                'end_time' => $range->endHour(),
            ])->all(),
        ]);
    }

    public function update(AvailabilityGridRequest $request, SyncAvailability $syncAvailability): RedirectResponse
    {
        $syncAvailability->handle($this->profile($request), $request->ranges());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Availability saved. Trainers can see these preferences when planning sessions.'),
        ]);

        return redirect()->route('coach.availability.edit');
    }

    private function profile(Request $request): CoachProfile
    {
        return CoachProfile::query()->where('user_id', $request->user()->id)->firstOrFail();
    }
}
