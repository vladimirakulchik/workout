<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTrainer;
use Database\Factories\TrainerPlayerAssociationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $trainer_id
 * @property int $player_profile_id
 * @property int|null $share_link_id
 * @property Carbon $connected_at
 * @property string $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['trainer_id', 'player_profile_id', 'share_link_id', 'connected_at', 'status'])]
class TrainerPlayerAssociation extends Model
{
    /** @use HasFactory<TrainerPlayerAssociationFactory> */
    use BelongsToTrainer, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * @return BelongsTo<PlayerProfile, $this>
     */
    public function playerProfile(): BelongsTo
    {
        return $this->belongsTo(PlayerProfile::class);
    }

    /**
     * @return BelongsTo<ShareLink, $this>
     */
    public function shareLink(): BelongsTo
    {
        return $this->belongsTo(ShareLink::class);
    }
}
