<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Enums\ApprovalStatus;
use App\Events\ChildPurchaseDenied;
use App\Models\ChildPurchaseApproval;
use App\Notifications\ChildPurchaseApprovalResolved;

class ExpireChildPurchaseApprovals
{
    /**
     * Auto-deny every pending request past its deadline and tell the child and the parent.
     *
     * @return int number of requests expired
     */
    public function handle(): int
    {
        $expired = 0;

        ChildPurchaseApproval::query()
            ->withoutGlobalScopes()
            ->dueToExpire()
            ->with(['playerProfile.user', 'parent'])
            ->eachById(function (ChildPurchaseApproval $approval) use (&$expired): void {
                $claimed = ChildPurchaseApproval::query()
                    ->withoutGlobalScopes()
                    ->whereKey($approval->id)
                    ->dueToExpire()
                    ->update(['status' => ApprovalStatus::Expired, 'responded_at' => now()]);

                if ($claimed === 0) {
                    return;
                }

                $approval->refresh();

                $notification = new ChildPurchaseApprovalResolved($approval);
                $approval->playerProfile->user?->notify($notification);
                $approval->parent->notify($notification);

                ChildPurchaseDenied::dispatch($approval);

                $expired++;
            });

        return $expired;
    }
}
