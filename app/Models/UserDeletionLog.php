<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserDeletionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $original_user_id
 * @property string $original_email
 * @property int|null $deleted_by
 * @property string $reason
 * @property array<string, mixed> $original_data
 * @property Carbon $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['original_user_id', 'original_email', 'deleted_by', 'reason', 'original_data', 'deleted_at'])]
class UserDeletionLog extends Model
{
    /** @use HasFactory<UserDeletionLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'original_data' => 'encrypted:array',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
