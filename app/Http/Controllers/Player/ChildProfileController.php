<?php

declare(strict_types=1);

namespace App\Http\Controllers\Player;

use App\Actions\Admin\DeactivateUser;
use App\Actions\Player\CreateChildProfile;
use App\Actions\Profile\StoreProfilePhoto;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\StoreChildProfileRequest;
use App\Http\Requests\Player\UpdateChildProfileRequest;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChildProfileController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PlayerProfile::class);

        $children = $request->user()->childProfiles()
            ->with(['user', 'trainers'])
            ->orderBy('first_name')
            ->get();

        return Inertia::render('player/Family/Index', [
            'children' => $children->map(fn (PlayerProfile $child): array => [
                'id' => $child->id,
                'name' => $child->first_name.' '.$child->last_name,
                'age' => $child->age,
                'school' => $child->school,
                'photo_url' => $child->photoUrl(),
                'has_login' => $child->user !== null,
                'allow_token_spending' => $child->allow_token_spending,
                'trainers' => $child->trainers->map(fn (User $trainer): string => $trainer->displayName())->values()->all(),
            ])->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', PlayerProfile::class);

        $trainers = User::query()
            ->whereIn('id', $request->user()->connectedTrainerIds())
            ->orderBy('first_name')
            ->get();

        return Inertia::render('player/Family/Create', [
            'parentLastName' => $request->user()->last_name,
            'trainers' => $trainers->map(fn (User $trainer): array => [
                'id' => $trainer->id,
                'name' => $trainer->displayName(),
            ])->all(),
        ]);
    }

    public function store(StoreChildProfileRequest $request, CreateChildProfile $createChildProfile): RedirectResponse
    {
        $data = $request->validated();

        if (! $request->boolean('confirm')) {
            $duplicates = $createChildProfile->findDuplicates($request->user(), $data['first_name'], $data['birth_date']);

            if ($duplicates->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'duplicate' => __('You already have a child named :name born in the same year. Create this profile anyway?', [
                        'name' => $duplicates->first()->first_name,
                    ]),
                ]);
            }
        }

        $createChildProfile->handle($request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Child profile created.')]);

        return redirect()->route('player.family.index');
    }

    public function edit(Request $request, PlayerProfile $childProfile): Response
    {
        Gate::authorize('update', $childProfile);

        return Inertia::render('player/Family/Edit', [
            'child' => [
                'id' => $childProfile->id,
                'first_name' => $childProfile->first_name,
                'last_name' => $childProfile->last_name,
                'birth_date' => $childProfile->birth_date?->toDateString(),
                'gender' => $childProfile->gender,
                'school' => $childProfile->school,
                'photo_url' => $childProfile->photoUrl(),
                'allow_token_spending' => $childProfile->allow_token_spending,
                'has_login' => $childProfile->user_id !== null,
            ],
        ]);
    }

    public function update(UpdateChildProfileRequest $request, PlayerProfile $childProfile, StoreProfilePhoto $storePhoto): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($childProfile, $data): void {
            $childProfile->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'school' => $data['school'] ?? null,
                'allow_token_spending' => $data['allow_token_spending'],
            ]);

            $childProfile->user?->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ]);
        });

        if ($request->hasFile('photo')) {
            $storePhoto->handle($childProfile, $request->file('photo'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Child profile updated.')]);

        return redirect()->route('player.family.index');
    }

    public function destroy(Request $request, PlayerProfile $childProfile, DeactivateUser $deactivateUser, StoreProfilePhoto $storePhoto): RedirectResponse
    {
        Gate::authorize('delete', $childProfile);

        $login = $childProfile->user;

        if ($login?->status === UserStatus::Active) {
            $deactivateUser->handle($request->user(), $login);
        }

        if ($childProfile->photo_path !== null) {
            $storePhoto->delete($childProfile->photo_path);
        }

        $childProfile->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Child profile removed.')]);

        return redirect()->route('player.family.index');
    }
}
