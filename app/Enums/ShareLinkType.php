<?php

declare(strict_types=1);

namespace App\Enums;

enum ShareLinkType: string
{
    case Player = 'player';
    case Coach = 'coach';

    public function label(): string
    {
        return match ($this) {
            self::Player => 'Player',
            self::Coach => 'Coach',
        };
    }
}
