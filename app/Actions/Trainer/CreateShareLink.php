<?php

declare(strict_types=1);

namespace App\Actions\Trainer;

use App\Enums\ShareLinkType;
use App\Models\ShareLink;
use App\Models\User;

class CreateShareLink
{
    public const COACH_LINK_VALID_DAYS = 7;

    /**
     * Player links are one static, unlimited link per trainer; coach links are single use,
     * expire after seven days and are tied to the invited email address.
     *
     * @param  array{name?: string|null, message?: string|null, target_email?: string|null}  $data
     */
    public function handle(User $trainer, ShareLinkType $type, array $data = []): ShareLink
    {
        if ($type === ShareLinkType::Player) {
            return ShareLink::query()->withoutGlobalScopes()->firstOrCreate(
                ['trainer_id' => $trainer->id, 'type' => ShareLinkType::Player, 'is_active' => true],
                ['code' => ShareLink::generateCode(), 'created_by' => $trainer->id],
            );
        }

        return ShareLink::query()->withoutGlobalScopes()->create([
            'code' => ShareLink::generateCode(),
            'type' => ShareLinkType::Coach,
            'trainer_id' => $trainer->id,
            'created_by' => $trainer->id,
            'target_email' => $data['target_email'] ?? null,
            'name' => $data['name'] ?? null,
            'message' => $data['message'] ?? null,
            'expires_at' => now()->addDays(self::COACH_LINK_VALID_DAYS),
            'max_uses' => 1,
        ]);
    }

    /**
     * Revoke the trainer's current static player link and issue a new one.
     */
    public function regeneratePlayerLink(User $trainer): ShareLink
    {
        ShareLink::query()->withoutGlobalScopes()
            ->where('trainer_id', $trainer->id)
            ->where('type', ShareLinkType::Player)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return $this->handle($trainer, ShareLinkType::Player);
    }
}
