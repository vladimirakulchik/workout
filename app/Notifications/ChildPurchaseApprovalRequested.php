<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChildPurchaseApproval;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChildPurchaseApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ChildPurchaseApproval $approval) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':child needs your approval', ['child' => $this->approval->playerProfile->first_name]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->first_name]))
            ->line($this->message())
            ->line(__('Please respond within :hours hours, otherwise the request is denied automatically.', ['hours' => ChildPurchaseApproval::EXPIRY_HOURS]))
            ->action(__('Review Request'), route('player.approvals.index'));
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
        return __(':child wants to buy ":description" for :amount (trainer: :trainer).', [
            'child' => $this->approval->playerProfile->first_name,
            'description' => $this->approval->description,
            'amount' => $this->approval->formattedAmount(),
            'trainer' => $this->approval->trainer->displayName(),
        ]);
    }
}
