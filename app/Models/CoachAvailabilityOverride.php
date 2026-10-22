<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CoachAvailabilityOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A trainer assigned a coach to an event outside the coach's declared availability.
 * `event_id` has no foreign key until the events epic introduces the table.
 *
 * @property int $id
 * @property int $event_id
 * @property int $coach_id
 * @property int $overridden_by
 * @property string $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_id', 'coach_id', 'overridden_by', 'reason'])]
class CoachAvailabilityOverride extends Model
{
    /** @use HasFactory<CoachAvailabilityOverrideFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
