<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Informs the parent that the child spent tokens without needing approval.
 */
class ChildTokenPurchaseMade extends Notification
{
    use Queueable;

    public function __construct(
        public string $childName,
        public string $description,
        public int $tokens,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':child spent :tokens tokens on ":description".', [
                'child' => $this->childName,
                'tokens' => $this->tokens,
                'description' => $this->description,
            ]),
            'url' => route('player.approvals.index', absolute: false),
        ];
    }
}
