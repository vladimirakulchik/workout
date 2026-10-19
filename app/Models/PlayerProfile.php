<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlayerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $parent_user_id
 * @property string $first_name
 * @property string $last_name
 * @property Carbon $birth_date
 * @property string $gender
 * @property string|null $skill_level
 * @property string|null $school
 * @property string|null $jersey_number
 * @property string|null $photo_path
 * @property bool $is_child
 * @property string $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property bool $allow_token_spending
 * @property-read int $age
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'parent_user_id', 'first_name', 'last_name', 'birth_date', 'gender', 'skill_level', 'school', 'jersey_number', 'photo_path', 'is_child', 'emergency_contact_name', 'emergency_contact_phone', 'allow_token_spending'])]
class PlayerProfile extends Model
{
    /** @use HasFactory<PlayerProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_child' => 'boolean',
            'allow_token_spending' => 'boolean',
        ];
    }

    /**
     * Age in whole years.
     *
     * @return Attribute<int, never>
     */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): int => $this->birth_date->age);
    }

    /**
     * The login that owns this profile (self player or child with own login).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }
}
