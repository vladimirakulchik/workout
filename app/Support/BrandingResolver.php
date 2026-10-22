<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserRole;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Storage;

/**
 * Finds the trainer whose branding applies to a login: a trainer themself, a coach's trainer or a player's current
 * context trainer. Admins (and impersonated users, who are simply the target user) fall back to the default.
 *
 * @phpstan-type Branding array{logo_url: string|null, color: string|null}
 */
class BrandingResolver
{
    public function __construct(private readonly ContextResolver $contexts) {}

    /**
     * @return Branding
     */
    public function resolve(User $user, Session $session): array
    {
        $trainerId = $this->trainerId($user, $session);
        $profile = $trainerId === null ? null : TrainerProfile::query()->where('user_id', $trainerId)->first();

        return [
            'logo_url' => $profile?->logo_path === null ? null : Storage::disk('public')->url($profile->logo_path),
            'color' => $profile?->brand_color,
        ];
    }

    private function trainerId(User $user, Session $session): ?int
    {
        if ($user->isTrainer()) {
            return $user->id;
        }

        if ($user->role === UserRole::Coach) {
            return $user->coachProfile()->value('trainer_id');
        }

        if ($user->isPlayer()) {
            return $this->contexts->resolve($user, $session)['trainer_id'] ?? null;
        }

        return null;
    }
}
