<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\CancelUpcomingRsvps;
use App\Events\ChildRemovedFromTrainer;

class CancelRsvpsForRemovedChild
{
    public function __construct(private CancelUpcomingRsvps $cancelUpcomingRsvps) {}

    public function handle(ChildRemovedFromTrainer $event): void
    {
        $this->cancelUpcomingRsvps->handle($event->child, $event->trainer);
    }
}
