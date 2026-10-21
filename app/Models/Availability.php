<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One available time range of a player or coach on a weekday (0 = Sunday ... 6 = Saturday).
 *
 * @property int $id
 * @property string $availabilityable_type
 * @property int $availabilityable_id
 * @property int $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['availabilityable_type', 'availabilityable_id', 'day_of_week', 'start_time', 'end_time'])]
class Availability extends Model
{
    /** @use HasFactory<AvailabilityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function availabilityable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Start time as `HH:MM`.
     */
    public function startHour(): string
    {
        return substr($this->start_time, 0, 5);
    }

    /**
     * End time as `HH:MM`.
     */
    public function endHour(): string
    {
        return substr($this->end_time, 0, 5);
    }
}
