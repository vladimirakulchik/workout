<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class ChildRemovedFromTrainer
{
    use Dispatchable;

    public function __construct(
        public PlayerProfile $child,
        public User $trainer,
    ) {}
}
