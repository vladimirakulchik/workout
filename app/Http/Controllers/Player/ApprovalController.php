<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Actions\Player\DecideChildPurchaseApproval;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\ApprovalDecisionRequest;
use App\Http\Requests\Player\RequestMoreInfoRequest;
use App\Models\ChildPurchaseApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    /**
     * Parents see their children's requests with decision buttons; a child login sees its own read-only.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ChildPurchaseApproval::class);

        $user = $request->user();
        $isChild = $user->isChildAccount();

        $approvals = ChildPurchaseApproval::query()
            ->with(['playerProfile', 'trainer'])
            ->when(
                $isChild,
                fn ($query) => $query->where('player_profile_id', $user->selfPlayerProfile?->id),
                fn ($query) => $query->where('parent_user_id', $user->id),
            )
            ->latest('requested_at')
            ->latest('id')
            ->get();

        $mapped = $approvals->map(fn (ChildPurchaseApproval $approval): array => [
            'id' => $approval->id,
            'child_name' => trim($approval->playerProfile->first_name.' '.$approval->playerProfile->last_name),
            'trainer_name' => $approval->trainer->displayName(),
            'description' => $approval->description,
            'amount' => $approval->formattedAmount(),
            'payment_type' => $approval->payment_type->value,
            'status' => $approval->status->value,
            'status_label' => $approval->status->label(),
            'requested_at' => $approval->requested_at->toIso8601String(),
            'responded_at' => $approval->responded_at?->toIso8601String(),
            'expires_at' => $approval->expires_at->toIso8601String(),
            'parent_notes' => $approval->parent_notes,
            'can_respond' => ! $isChild && $approval->isDecidable(),
        ]);

        return Inertia::render('player/Approvals/Index', [
            'pending' => $mapped->filter(fn (array $row): bool => $row['status'] === ApprovalStatus::Pending->value)->values(),
            'history' => $mapped->reject(fn (array $row): bool => $row['status'] === ApprovalStatus::Pending->value)->values(),
            'readOnly' => $isChild,
        ]);
    }

    public function approve(ApprovalDecisionRequest $request, ChildPurchaseApproval $childPurchaseApproval, DecideChildPurchaseApproval $decide): RedirectResponse
    {
        Gate::authorize('respond', $childPurchaseApproval);

        $decide->handle($childPurchaseApproval, true, $request->input('notes'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase approved.')]);

        return redirect()->route('player.approvals.index');
    }

    public function deny(ApprovalDecisionRequest $request, ChildPurchaseApproval $childPurchaseApproval, DecideChildPurchaseApproval $decide): RedirectResponse
    {
        Gate::authorize('respond', $childPurchaseApproval);

        $decide->handle($childPurchaseApproval, false, $request->input('notes'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase denied.')]);

        return redirect()->route('player.approvals.index');
    }

    public function requestInfo(RequestMoreInfoRequest $request, ChildPurchaseApproval $childPurchaseApproval, DecideChildPurchaseApproval $decide): RedirectResponse
    {
        Gate::authorize('respond', $childPurchaseApproval);

        $decide->requestMoreInfo($childPurchaseApproval, (string) $request->input('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your question was sent to your child.')]);

        return redirect()->route('player.approvals.index');
    }
}
