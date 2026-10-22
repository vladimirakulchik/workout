<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChildPurchaseApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the child that the parent wants more information before deciding.
 */
class ChildPurchaseMoreInfoRequested extends Notification
{
    use Queueable;

    public function __construct(public ChildPurchaseApproval $approval) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{message: string, url: string, approval_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('Your parent asked for more information about ":description": :note', [
                'description' => $this->approval->description,
                'note' => (string) $this->approval->parent_notes,
            ]),
            'url' => route('player.approvals.index', absolute: false),
            'approval_id' => $this->approval->id,
        ];
    }
}
