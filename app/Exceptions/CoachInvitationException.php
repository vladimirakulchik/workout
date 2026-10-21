<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class CoachInvitationException extends RuntimeException
{
    public static function emailMismatch(): self
    {
        return new self(__('This invitation was sent to a different email address.'));
    }

    public static function activeUnderAnotherTrainer(): self
    {
        return new self(__('This coach is already active under another trainer.'));
    }

    public static function unavailable(): self
    {
        return new self(__('This invitation is no longer available. Please ask your trainer to resend it.'));
    }

    public static function wrongRole(): self
    {
        return new self(__('An account with this email already exists and is not a coach account.'));
    }
}
