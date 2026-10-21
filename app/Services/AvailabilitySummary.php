<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Availability;
use Illuminate\Support\Collection;

/**
 * Human readable "Best Times" text, e.g. "Mon 5-8pm, Wed 6-9pm".
 */
class AvailabilitySummary
{
    /** Display order, Monday first; keys are `day_of_week` (0 = Sunday). */
    private const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];

    /**
     * @param  iterable<Availability>  $availabilities
     */
    public function summarize(iterable $availabilities): string
    {
        $byDay = Collection::make($availabilities)->groupBy('day_of_week');
        $parts = [];

        foreach (self::DAYS as $day => $label) {
            $ranges = ($byDay[$day] ?? Collection::make())->sortBy('start_time');

            foreach ($ranges as $range) {
                $parts[] = $label.' '.$this->formatRange($range->startHour(), $range->endHour());
            }
        }

        return implode(', ', $parts);
    }

    private function formatRange(string $start, string $end): string
    {
        [$startClock, $startSuffix] = $this->clock($start);
        [$endClock, $endSuffix] = $this->clock($end);

        return $startSuffix === $endSuffix
            ? $startClock.'-'.$endClock.$endSuffix
            : $startClock.$startSuffix.'-'.$endClock.$endSuffix;
    }

    /**
     * @return array{0: string, 1: string} clock text without suffix, and am/pm
     */
    private function clock(string $time): array
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        $suffix = $hour % 24 >= 12 ? 'pm' : 'am';
        $display = $hour % 12 === 0 ? 12 : $hour % 12;

        return [$minute === 0 ? (string) $display : sprintf('%d:%02d', $display, $minute), $suffix];
    }
}
