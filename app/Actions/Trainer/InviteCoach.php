<?php

declare(strict_types=1);

namespace App\Actions\Trainer;

use App\Enums\ShareLinkType;
use App\Mail\CoachInvitationMail;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InviteCoach
{
    public function __construct(private readonly CreateShareLink $createShareLink) {}

    /**
     * Create a single-use coach link for the email address and mail it to the coach.
     *
     * @param  array<string, mixed>  $data  Validated InviteCoachRequest data.
     */
    public function handle(User $trainer, array $data): ShareLink
    {
        $link = $this->createShareLink->handle($trainer, ShareLinkType::Coach, [
            'target_email' => (string) $data['target_email'],
            'name' => $data['name'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        Mail::to($link->target_email)->send(new CoachInvitationMail($link, $trainer));

        return $link;
    }

    /**
     * Issue a fresh link with the same details; the old link stops working.
     */
    public function resend(ShareLink $old): ShareLink
    {
        return DB::transaction(function () use ($old): ShareLink {
            $old->forceFill(['is_active' => false])->save();

            return $this->handle($old->trainer, [
                'target_email' => (string) $old->target_email,
                'name' => $old->name,
                'message' => $old->message,
            ]);
        });
    }
}
