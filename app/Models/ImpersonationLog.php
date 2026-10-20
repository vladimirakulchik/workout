<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ImpersonationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $admin_id
 * @property int $impersonated_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property string $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['admin_id', 'impersonated_id', 'started_at', 'ended_at', 'duration_seconds', 'ip_address'])]
class ImpersonationLog extends Model
{
    /** @use HasFactory<ImpersonationLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_id');
    }

    /**
     * Stamp the end time and elapsed seconds; a no-op for an already closed log.
     */
    public function close(): void
    {
        if ($this->ended_at !== null) {
            return;
        }

        $endedAt = now();

        $this->forceFill([
            'ended_at' => $endedAt,
            'duration_seconds' => max(0, $endedAt->getTimestamp() - $this->started_at->getTimestamp()),
        ])->save();
    }
}
