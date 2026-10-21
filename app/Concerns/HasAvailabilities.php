<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Availability;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For models that own weekly availability ranges (player and coach profiles).
 */
trait HasAvailabilities
{
    /**
     * @return MorphMany<Availability, $this>
     */
    public function availabilities(): MorphMany
    {
        return $this->morphMany(Availability::class, 'availabilityable')
            ->orderBy('day_of_week')
            ->orderBy('start_time');
    }
}
