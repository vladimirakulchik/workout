<?php

declare(strict_types=1);

namespace App\Actions\Availability;

use App\Models\CoachProfile;
use App\Models\PlayerProfile;
use Illuminate\Support\Facades\DB;

class SyncAvailability
{
    /**
     * Replace the owner's whole set of ranges.
     *
     * @param  list<array{day_of_week: int, start_time: string, end_time: string}>  $ranges  times as `HH:MM`
     */
    public function handle(PlayerProfile|CoachProfile $owner, array $ranges): void
    {
        DB::transaction(function () use ($owner, $ranges): void {
            $owner->availabilities()->delete();

            $owner->availabilities()->createMany(array_map(fn (array $range): array => [
                'day_of_week' => $range['day_of_week'],
                'start_time' => $range['start_time'].':00',
                'end_time' => $range['end_time'].':00',
            ], $ranges));
        });
    }
}
