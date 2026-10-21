<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainer;

use App\Actions\Trainer\InviteCoach;
use App\Actions\Trainer\RemoveCoach;
use App\Enums\ShareLinkType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\InviteCoachRequest;
use App\Models\Availability;
use App\Models\CoachProfile;
use App\Models\ShareLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CoachController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', CoachProfile::class);

        $coaches = CoachProfile::query()
            ->with('user')
            ->orderBy('joined_at')
            ->get();

        $invitations = ShareLink::query()
            ->where('type', ShareLinkType::Coach)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereColumn('uses_count', '>=', 'max_uses'))
            ->latest('id')
            ->limit(100)
            ->get();

        return Inertia::render('trainer/Coaches/Index', [
            'coaches' => $coaches->map(fn (CoachProfile $profile): array => [
                'id' => $profile->id,
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'joined_at' => $profile->joined_at?->toIso8601String(),
            ])->all(),
            'invitations' => $invitations->map(fn (ShareLink $link): array => [
                'id' => $link->id,
                'name' => $link->name,
                'email' => $link->target_email,
                'message' => $link->message,
                'status' => $link->status(),
                'expires_at' => $link->expires_at?->toIso8601String(),
                'can_resend' => in_array($link->status(), [ShareLink::STATUS_PENDING, ShareLink::STATUS_EXPIRED], true),
            ])->all(),
        ]);
    }

    public function show(CoachProfile $coachProfile): Response
    {
        Gate::authorize('view', $coachProfile);

        $coachProfile->load(['user', 'availabilities']);

        return Inertia::render('trainer/Coaches/Show', [
            'coach' => [
                'id' => $coachProfile->id,
                'name' => $coachProfile->user->name,
                'email' => $coachProfile->user->email,
                'bio' => $coachProfile->bio,
                'credentials' => $coachProfile->credentials,
                'certifications' => $coachProfile->certifications,
                'is_public' => $coachProfile->is_public,
                'joined_at' => $coachProfile->joined_at?->toIso8601String(),
            ],
            'ranges' => $coachProfile->availabilities->map(fn (Availability $range): array => [
                'day_of_week' => $range->day_of_week,
                'start_time' => $range->startHour(),
                'end_time' => $range->endHour(),
            ])->all(),
        ]);
    }

    public function store(InviteCoachRequest $request, InviteCoach $inviteCoach): RedirectResponse
    {
        $inviteCoach->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    public function resend(ShareLink $shareLink, InviteCoach $inviteCoach): RedirectResponse
    {
        Gate::authorize('deactivate', $shareLink);

        abort_unless(
            $shareLink->type === ShareLinkType::Coach
                && in_array($shareLink->status(), [ShareLink::STATUS_PENDING, ShareLink::STATUS_EXPIRED], true),
            422,
        );

        $inviteCoach->resend($shareLink);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation resent. The previous link no longer works.')]);

        return back();
    }

    public function destroy(CoachProfile $coachProfile, RemoveCoach $removeCoach): RedirectResponse
    {
        Gate::authorize('remove', $coachProfile);

        $removeCoach->handle($coachProfile);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Coach removed.')]);

        return back();
    }
}
