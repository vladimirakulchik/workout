<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Availability;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Compares an event window with the coach's weekly availability ranges (wall-clock times).
 */
class CoachAvailabilityChecker
{
    /**
     * Whether the whole window is covered by the coach's availability. A window running past
     * midnight must be covered on every day it touches.
     */
    public function check(User $coach, CarbonInterface $start, CarbonInterface $end): bool
    {
        if ($end <= $start || $coach->coachProfile === null) {
            return false;
        }

        $ranges = $coach->coachProfile->availabilities()->get()->groupBy('day_of_week');

        $day = $start->copy()->startOfDay();

        while ($day < $end) {
            $nextDay = $day->copy()->addDay();
            $segmentStart = $start > $day ? $this->minutes($start) : 0;
            $segmentEnd = $end < $nextDay ? $this->minutes($end) : 1440;

            $covered = $ranges->get($day->dayOfWeek, collect())->contains(
                fn (Availability $range): bool => $this->parse($range->start_time) <= $segmentStart
                    && $this->parse($range->end_time) >= $segmentEnd,
            );

            if (! $covered) {
                return false;
            }

            $day = $nextDay;
        }

        return true;
    }

    private function minutes(CarbonInterface $moment): int
    {
        return $moment->hour * 60 + $moment->minute;
    }

    private function parse(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }
}
