<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Actions\Player\RequestChildPurchaseApproval;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\PurchaseRequestRequest;
use App\Models\PlayerProfile;
use App\Models\User;
use App\Support\CurrentContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Stand-in for the Epic-05 checkout: files a purchase for the current player context.
 */
class PurchaseRequestController extends Controller
{
    public function __invoke(
        PurchaseRequestRequest $request,
        CurrentContext $context,
        RequestChildPurchaseApproval $requestApproval,
    ): RedirectResponse {
        abort_if($context->playerId() === null || $context->trainerId() === null, 403);

        $child = PlayerProfile::query()->findOrFail($context->playerId());
        $trainer = User::query()->findOrFail($context->trainerId());

        $approval = $requestApproval->handle(
            $child,
            $trainer,
            (string) $request->string('description'),
            $request->integer('amount_cents'),
            (string) $request->string('currency'),
            PaymentType::from((string) $request->string('payment_type')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $approval === null
            ? __('Purchase made with tokens. Your parent was notified.')
            : __('Your parent was asked to approve this purchase.')]);

        return back();
    }
}
