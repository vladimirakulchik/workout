<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Trainer = 'trainer';
    case Coach = 'coach';
    case Player = 'player';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Trainer => 'Trainer',
            self::Coach => 'Coach',
            self::Player => 'Player',
        };
    }
}
