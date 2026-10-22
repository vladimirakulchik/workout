<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTrainer;
use App\Enums\ApprovalStatus;
use App\Enums\PaymentType;
use Database\Factories\ChildPurchaseApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A purchase a child wants to make that needs the parent's decision.
 * `amount_cents` is minor currency units for USD and a token count for token purchases.
 *
 * @property int $id
 * @property int $player_profile_id
 * @property int $parent_user_id
 * @property int $trainer_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $description
 * @property int $amount_cents
 * @property string $currency
 * @property PaymentType $payment_type
 * @property ApprovalStatus $status
 * @property Carbon $requested_at
 * @property Carbon|null $responded_at
 * @property Carbon $expires_at
 * @property string|null $parent_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['player_profile_id', 'parent_user_id', 'trainer_id', 'subject_type', 'subject_id', 'description', 'amount_cents', 'currency', 'payment_type', 'status', 'requested_at', 'responded_at', 'expires_at', 'parent_notes'])]
class ChildPurchaseApproval extends Model
{
    /** @use HasFactory<ChildPurchaseApprovalFactory> */
    use BelongsToTrainer, HasFactory;

    /** Hours a parent has to respond before the request is auto-denied. */
    public const EXPIRY_HOURS = 48;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'payment_type' => PaymentType::class,
            'status' => ApprovalStatus::class,
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ChildPurchaseApproval>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ApprovalStatus::Pending);
    }

    /**
     * Pending requests whose response window has passed.
     *
     * @param  Builder<ChildPurchaseApproval>  $query
     */
    public function scopeDueToExpire(Builder $query): void
    {
        $query->where('status', ApprovalStatus::Pending)->where('expires_at', '<=', now());
    }

    /**
     * Whether the parent can still decide on this request.
     */
    public function isDecidable(): bool
    {
        return $this->status === ApprovalStatus::Pending && $this->expires_at->isFuture();
    }

    /**
     * Human readable price, e.g. "$12.50" or "30 tokens".
     */
    public function formattedAmount(): string
    {
        if ($this->payment_type === PaymentType::Tokens) {
            return $this->amount_cents.' '.__('tokens');
        }

        $amount = number_format($this->amount_cents / 100, 2);

        return $this->currency === 'USD' ? '$'.$amount : $amount.' '.$this->currency;
    }

    /**
     * @return BelongsTo<PlayerProfile, $this>
     */
    public function playerProfile(): BelongsTo
    {
        return $this->belongsTo(PlayerProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * What is being bought (an event once Epic-02 exists).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
