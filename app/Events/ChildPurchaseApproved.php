<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ChildPurchaseApproval;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A parent approved a child's purchase; the checkout (Epic-05) listens and completes it.
 */
class ChildPurchaseApproved
{
    use Dispatchable;

    public function __construct(public ChildPurchaseApproval $approval) {}
}
