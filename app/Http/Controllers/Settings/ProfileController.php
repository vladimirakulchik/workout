<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Profile\UpdateUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, UpdateUserProfile $updateProfile): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'profile' => [
                'phone' => $user->phone,
                'role_label' => $user->role->label(),
                'created_at' => $user->created_at?->toDateString(),
                'photo_url' => $user->photoUrl(),
                'email_verified' => $user->hasVerifiedEmail(),
            ],
            'restricted' => Gate::forUser($user)->allows('child.restricted'),
            'roleProfile' => $updateProfile->roleProfileProps($user),
        ]);
    }

    /**
     * Update the user's profile information. Email is read-only.
     */
    public function update(ProfileUpdateRequest $request, UpdateUserProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle($request->user(), $request->validated(), $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
