<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTrainer;
use App\Enums\ShareLinkType;
use Database\Factories\ShareLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $code
 * @property ShareLinkType $type
 * @property int $trainer_id
 * @property int $created_by
 * @property string|null $target_email
 * @property string|null $name
 * @property string|null $message
 * @property Carbon|null $expires_at
 * @property int|null $max_uses
 * @property int $uses_count
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'type', 'trainer_id', 'created_by', 'target_email', 'name', 'message', 'expires_at', 'max_uses', 'uses_count', 'is_active'])]
class ShareLink extends Model
{
    /** @use HasFactory<ShareLinkFactory> */
    use BelongsToTrainer, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    private const CODE_LENGTH = 10;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShareLinkType::class,
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * A URL-safe random code that is not used by any other link.
     */
    public static function generateCode(): string
    {
        do {
            $code = Str::random(self::CODE_LENGTH);
        } while (static::query()->withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }

    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isExpired() && ! $this->isExhausted();
    }

    public function url(): string
    {
        return route('join.show', ['code' => $this->code]);
    }

    /**
     * One of the STATUS_* constants.
     */
    public function status(): string
    {
        return match (true) {
            ! $this->is_active => self::STATUS_REVOKED,
            $this->isExhausted() => self::STATUS_ACCEPTED,
            $this->isExpired() => self::STATUS_EXPIRED,
            default => self::STATUS_PENDING,
        };
    }
}
