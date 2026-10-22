<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ApprovalStatus;
use App\Models\ChildPurchaseApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the child and the parent how a purchase request ended (approved, denied or expired).
 */
class ChildPurchaseApprovalResolved extends Notification
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
            'message' => $this->message(),
            'url' => route('player.approvals.index', absolute: false),
            'approval_id' => $this->approval->id,
        ];
    }

    private function message(): string
    {
        $replacements = [
            'description' => $this->approval->description,
            'amount' => $this->approval->formattedAmount(),
            'child' => $this->approval->playerProfile->first_name,
        ];

        return match ($this->approval->status) {
            ApprovalStatus::Approved => __('Purchase ":description" (:amount) for :child was approved.', $replacements),
            ApprovalStatus::Expired => __('Purchase ":description" (:amount) for :child expired without a response and was denied.', $replacements),
            default => __('Purchase ":description" (:amount) for :child was denied.', $replacements),
        };
    }
}
