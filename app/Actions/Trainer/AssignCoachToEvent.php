<?php

declare(strict_types=1);

namespace App\Actions\Trainer;

use App\Contracts\WriteCoachToEvent;
use App\Models\CoachAvailabilityOverride;
use App\Models\CoachProfile;
use App\Models\User;
use App\Services\CoachAvailabilityChecker;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignCoachToEvent
{
    public function __construct(
        private readonly CoachAvailabilityChecker $checker,
        private readonly WriteCoachToEvent $writeCoachToEvent,
    ) {}

    /**
     * Assign the coach to the event. When the window is outside the coach's availability a reason
     * is required and an override row is logged; the coach is not notified.
     *
     * @return CoachAvailabilityOverride|null the override log row, or null when the coach was available
     *
     * @throws ValidationException
     */
    public function handle(
        User $trainer,
        CoachProfile $coachProfile,
        int $eventId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $reason = null,
    ): ?CoachAvailabilityOverride {
        $coach = $coachProfile->user;
        $conflict = ! $this->checker->check($coach, $start, $end);
        $reason = trim((string) $reason);

        if ($conflict && $reason === '') {
            throw ValidationException::withMessages(['reason' => __('A reason is required to assign an unavailable coach.')]);
        }

        return DB::transaction(function () use ($trainer, $coach, $eventId, $start, $end, $conflict, $reason): ?CoachAvailabilityOverride {
            $this->writeCoachToEvent->handle($eventId, $coach, $start, $end);

            if (! $conflict) {
                return null;
            }

            return CoachAvailabilityOverride::query()->create([
                'event_id' => $eventId,
                'coach_id' => $coach->id,
                'overridden_by' => $trainer->id,
                'reason' => $reason,
            ]);
        });
    }
}
