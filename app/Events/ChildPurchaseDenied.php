<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ChildPurchaseApproval;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A child's purchase ended without approval: the parent denied it, or it expired
 * (check `$approval->status`). The checkout (Epic-05) listens and cancels it.
 */
class ChildPurchaseDenied
{
    use Dispatchable;

    public function __construct(public ChildPurchaseApproval $approval) {}
}
