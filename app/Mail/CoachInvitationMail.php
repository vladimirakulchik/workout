<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoachInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ShareLink $link, public User $trainer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':trainer invited you to coach on :app', [
                'trainer' => $this->trainer->displayName(),
                'app' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.coach-invitation',
            with: [
                'coachName' => $this->link->name,
                'trainerName' => $this->trainer->displayName(),
                'personalMessage' => $this->link->message,
                'joinUrl' => $this->link->url(),
                'expiresAt' => $this->link->expires_at?->toFormattedDateString(),
            ],
        );
    }
}
