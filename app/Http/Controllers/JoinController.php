<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Player\AssociateWithTrainer;
use App\Actions\Player\RegisterPlayerViaShareLink;
use App\Enums\ShareLinkType;
use App\Http\Requests\JoinRegistrationRequest;
use App\Http\Requests\JoinSelectionRequest;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class JoinController extends Controller
{
    private const REASONS = [
        ShareLink::STATUS_REVOKED => 'This link has been deactivated. Please ask your trainer for a new one.',
        ShareLink::STATUS_ACCEPTED => 'This link has already been used. Please ask your trainer for a new one.',
        ShareLink::STATUS_EXPIRED => 'This link has expired. Please ask your trainer for a new one.',
    ];

    public function show(Request $request, string $code, AssociateWithTrainer $associate): Response
    {
        $link = ShareLink::findByCode($code);

        if ($link === null || ! $link->isUsable()) {
            return $this->invalid($request, $link);
        }

        $request->session()->put('join_code', $link->code);

        $user = $request->user();

        if ($user instanceof User) {
            return $this->joinAsAuthenticated($request, $link, $user, $associate);
        }

        if ($link->type === ShareLinkType::Player) {
            return Inertia::render('auth/JoinRegister', [
                'trainerName' => $link->trainer->displayName(),
                'code' => $link->code,
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ])->toResponse($request);
        }

        return Inertia::render('join/Show', [
            'trainerName' => $link->trainer->displayName(),
            'type' => $link->type->value,
            'typeLabel' => $link->type->label(),
        ])->toResponse($request);
    }

    public function register(JoinRegistrationRequest $request, string $code, RegisterPlayerViaShareLink $register): RedirectResponse|Response
    {
        $link = ShareLink::findByCode($code);

        if ($link === null || ! $link->isUsable()) {
            return $this->invalid($request, $link);
        }

        if ($link->type !== ShareLinkType::Player) {
            return $this->invalid($request, $link, __('This link is for coaches and cannot be used to register a player.'), 403);
        }

        $result = $register->handle($link, $request->validated());

        Auth::login($result['user']);
        $request->session()->regenerate();
        $request->session()->forget('join_code');
        $request->session()->put([
            'current_trainer_id' => $link->trainer_id,
            'current_player_id' => $result['profiles']->first()?->id,
        ]);

        return redirect()->route('verification.notice');
    }

    private function joinAsAuthenticated(Request $request, ShareLink $link, User $user, AssociateWithTrainer $associate): Response
    {
        if (! $user->isPlayer()) {
            return $this->invalid($request, $link, __('You are signed in with a :role account. Log out to use this link.', ['role' => strtolower($user->role->label())]), 403);
        }

        if ($link->type !== ShareLinkType::Player) {
            return $this->invalid($request, $link, __('This link is for coaches and cannot be used with a player account.'), 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->childProfiles()->exists()) {
            return redirect()->route('player.join.select', ['code' => $link->code]);
        }

        $profile = $user->selfPlayerProfile;

        if ($profile === null) {
            return $this->invalid($request, $link, __('Your account has no player profile to connect.'), 422);
        }

        return $this->finish($request, $link, $associate->handle($link, collect([$profile])), $profile);
    }

    /**
     * Checklist of the parent's own profile and children for a player link.
     */
    public function select(Request $request, string $code): Response|RedirectResponse
    {
        $link = ShareLink::findByCode($code);

        if ($link === null || ! $link->isUsable()) {
            return $this->invalid($request, $link);
        }

        if ($link->type !== ShareLinkType::Player) {
            return $this->invalid($request, $link, __('This link is for coaches and cannot be used with a player account.'), 403);
        }

        $profiles = $this->selectableProfiles($request->user());

        if ($profiles->isNotEmpty() && $profiles->every(fn (PlayerProfile $profile): bool => $profile->trainers->contains('id', $link->trainer_id))) {
            return $this->finish($request, $link, collect(), $profiles->first());
        }

        return Inertia::render('player/JoinSelect', [
            'trainerName' => $link->trainer->displayName(),
            'code' => $link->code,
            'profiles' => $profiles->map(fn (PlayerProfile $profile): array => [
                'id' => $profile->id,
                'name' => trim($profile->first_name.' '.$profile->last_name),
                'is_child' => $profile->is_child,
                'connected' => $profile->trainers->contains('id', $link->trainer_id),
            ])->values()->all(),
        ])->toResponse($request);
    }

    /**
     * Connect only the chosen profiles of the signed-in player.
     */
    public function associate(JoinSelectionRequest $request, string $code, AssociateWithTrainer $associate): Response|RedirectResponse
    {
        $link = ShareLink::findByCode($code);

        if ($link === null || ! $link->isUsable()) {
            return $this->invalid($request, $link);
        }

        if ($link->type !== ShareLinkType::Player) {
            return $this->invalid($request, $link, __('This link is for coaches and cannot be used with a player account.'), 403);
        }

        $chosen = $this->selectableProfiles($request->user())
            ->whereIn('id', $request->validated('profile_ids'))
            ->values();

        if ($chosen->isEmpty()) {
            throw ValidationException::withMessages(['profile_ids' => __('Choose at least one person.')]);
        }

        return $this->finish($request, $link, $associate->handle($link, $chosen), $chosen->first());
    }

    /**
     * Profiles the user may connect: their own and their children's.
     *
     * @return Collection<int, PlayerProfile>
     */
    private function selectableProfiles(User $user): Collection
    {
        return PlayerProfile::query()
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('parent_user_id', $user->id))
            ->with('trainers')
            ->orderBy('is_child')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @param  Collection<int, PlayerProfile>  $connected  Profiles that were newly connected.
     */
    private function finish(Request $request, ShareLink $link, Collection $connected, ?PlayerProfile $current): RedirectResponse
    {
        $request->session()->forget('join_code');
        $request->session()->put([
            'current_trainer_id' => $link->trainer_id,
            'current_player_id' => $current?->id,
        ]);

        $trainerName = $link->trainer->displayName();

        Inertia::flash('toast', $connected->isEmpty()
            ? ['type' => 'info', 'message' => __('You are already connected to :trainer.', ['trainer' => $trainerName])]
            : ['type' => 'success', 'message' => __('You are now connected to :trainer.', ['trainer' => $trainerName])]);

        return redirect()->route('player.dashboard');
    }

    private function invalid(Request $request, ?ShareLink $link, ?string $message = null, ?int $status = null): Response
    {
        $message ??= $link === null
            ? __('This link is not valid. Please check the address or ask your trainer for a new one.')
            : __(self::REASONS[$link->status()]);

        return Inertia::render('join/Invalid', ['message' => $message])
            ->toResponse($request)
            ->setStatusCode($status ?? ($link === null ? 404 : 410));
    }
}
