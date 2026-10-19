<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TrainerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $business_name
 * @property string|null $address
 * @property string|null $website
 * @property string|null $description
 * @property string|null $logo_path
 * @property string|null $brand_color
 * @property string|null $stripe_account_id
 * @property string|null $subscription_status
 * @property string|null $platform_fee_percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'business_name', 'address', 'website', 'description', 'logo_path', 'brand_color', 'stripe_account_id', 'subscription_status', 'platform_fee_percent'])]
class TrainerProfile extends Model
{
    /** @use HasFactory<TrainerProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
