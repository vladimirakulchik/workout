<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChildWantsToJoinMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $parent, public PlayerProfile $child, public User $trainer, public ShareLink $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':child wants to join :trainer\'s program', [
                'child' => $this->child->first_name,
                'trainer' => $this->trainer->displayName(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.child-wants-to-join',
            with: [
                'parentName' => $this->parent->first_name,
                'childName' => $this->child->first_name,
                'trainerName' => $this->trainer->displayName(),
                'joinUrl' => route('join.show', $this->link->code),
            ],
        );
    }
}
