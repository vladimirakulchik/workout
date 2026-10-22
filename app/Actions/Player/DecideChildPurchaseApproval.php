<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Enums\ApprovalStatus;
use App\Events\ChildPurchaseApproved;
use App\Events\ChildPurchaseDenied;
use App\Models\ChildPurchaseApproval;
use App\Notifications\ChildPurchaseApprovalResolved;
use App\Notifications\ChildPurchaseMoreInfoRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideChildPurchaseApproval
{
    /**
     * Record the parent's decision. A request can be decided once, and only before it expires.
     *
     * @throws ValidationException when the request was already answered or has expired
     */
    public function handle(ChildPurchaseApproval $approval, bool $approve, ?string $notes = null): ChildPurchaseApproval
    {
        $approval = DB::transaction(function () use ($approval, $approve, $notes): ChildPurchaseApproval {
            $locked = ChildPurchaseApproval::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($approval->id);

            $this->ensureDecidable($locked);

            $locked->update([
                'status' => $approve ? ApprovalStatus::Approved : ApprovalStatus::Denied,
                'responded_at' => now(),
                'parent_notes' => $this->clean($notes) ?? $locked->parent_notes,
            ]);

            return $locked;
        });

        $approval->loadMissing(['playerProfile.user', 'parent']);

        $approval->playerProfile->user?->notify(new ChildPurchaseApprovalResolved($approval));

        if ($approve) {
            ChildPurchaseApproved::dispatch($approval);
        } else {
            ChildPurchaseDenied::dispatch($approval);
        }

        return $approval;
    }

    /**
     * Store a question for the child and tell them; the request stays pending.
     *
     * @throws ValidationException when the request was already answered or has expired
     */
    public function requestMoreInfo(ChildPurchaseApproval $approval, string $note): ChildPurchaseApproval
    {
        $approval = DB::transaction(function () use ($approval, $note): ChildPurchaseApproval {
            $locked = ChildPurchaseApproval::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($approval->id);

            $this->ensureDecidable($locked);

            $locked->update(['parent_notes' => $this->clean($note)]);

            return $locked;
        });

        $approval->loadMissing('playerProfile.user');

        $approval->playerProfile->user?->notify(new ChildPurchaseMoreInfoRequested($approval));

        return $approval;
    }

    /**
     * @throws ValidationException
     */
    private function ensureDecidable(ChildPurchaseApproval $approval): void
    {
        if (! $approval->isDecidable()) {
            throw ValidationException::withMessages(['approval' => __('This request has already been answered or has expired.')]);
        }
    }

    private function clean(?string $notes): ?string
    {
        $notes = trim((string) $notes);

        return $notes === '' ? null : $notes;
    }
}
