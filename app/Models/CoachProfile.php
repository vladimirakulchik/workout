<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTrainer;
use Database\Factories\CoachProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $trainer_id
 * @property string|null $bio
 * @property string|null $credentials
 * @property string|null $certifications
 * @property bool $is_public
 * @property Carbon|null $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'trainer_id', 'bio', 'credentials', 'certifications', 'is_public', 'joined_at'])]
class CoachProfile extends Model
{
    /** @use HasFactory<CoachProfileFactory> */
    use BelongsToTrainer, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}
