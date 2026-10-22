<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentType;
use App\Models\ChildPurchaseApproval;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Notifications\ChildPurchaseApprovalRequested;
use App\Notifications\ChildTokenPurchaseMade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RequestChildPurchaseApproval
{
    /**
     * Ask the child's parent to approve a purchase. Token purchases skip approval when the child
     * is allowed to spend tokens; the parent is only informed.
     *
     * @return ChildPurchaseApproval|null the pending request, or null when no approval is needed
     *
     * @throws ValidationException when the profile is not a child with a parent
     */
    public function handle(
        PlayerProfile $child,
        User $trainer,
        string $description,
        int $amountCents,
        string $currency,
        PaymentType $paymentType,
        ?Model $subject = null,
    ): ?ChildPurchaseApproval {
        $parent = $child->is_child ? $child->parent : null;

        if ($parent === null) {
            throw ValidationException::withMessages(['player_profile_id' => __('Only children need purchase approval.')]);
        }

        if ($paymentType === PaymentType::Tokens && $child->allow_token_spending) {
            $parent->notify(new ChildTokenPurchaseMade($child->first_name, $description, $amountCents));

            return null;
        }

        $approval = ChildPurchaseApproval::query()->create([
            'player_profile_id' => $child->id,
            'parent_user_id' => $parent->id,
            'trainer_id' => $trainer->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'payment_type' => $paymentType,
            'status' => ApprovalStatus::Pending,
            'requested_at' => now(),
            'expires_at' => now()->addHours(ChildPurchaseApproval::EXPIRY_HOURS),
        ]);

        $approval->setRelation('playerProfile', $child)->setRelation('trainer', $trainer);

        $parent->notify(new ChildPurchaseApprovalRequested($approval));

        return $approval;
    }
}
