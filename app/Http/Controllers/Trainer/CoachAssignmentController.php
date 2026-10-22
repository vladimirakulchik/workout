<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainer;

use App\Actions\Trainer\AssignCoachToEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\AssignCoachRequest;
use App\Http\Requests\Trainer\CoachAssignmentCheckRequest;
use App\Services\CoachAvailabilityChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CoachAssignmentController extends Controller
{
    public function check(CoachAssignmentCheckRequest $request, CoachAvailabilityChecker $checker): JsonResponse
    {
        $coach = $request->coachProfile()->user;

        return response()->json([
            'available' => $checker->check($coach, $request->start(), $request->end()),
            'coach_name' => $coach->name,
        ]);
    }

    public function store(AssignCoachRequest $request, AssignCoachToEvent $assignCoachToEvent): RedirectResponse
    {
        $assignCoachToEvent->handle(
            $request->user(),
            $request->coachProfile(),
            (int) $request->validated('event_id'),
            $request->start(),
            $request->end(),
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coach assigned.')]);

        return back();
    }
}
