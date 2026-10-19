<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Profile\StoreProfilePhoto;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\CoachProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
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
            'roleProfile' => $this->roleProfileProps($user),
        ]);
    }

    /**
     * Update the user's profile information. Email is read-only.
     */
    public function update(ProfileUpdateRequest $request, StoreProfilePhoto $storePhoto): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated, $request, $storePhoto): void {
            $user->fill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? null,
            ])->save();

            $this->updateRoleProfile($user, $validated);

            if ($request->hasFile('photo')) {
                $storePhoto->handle($user, $request->file('photo'));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function roleProfileProps(User $user): ?array
    {
        return match ($user->role) {
            UserRole::Player => $user->selfPlayerProfile?->only([
                'school', 'jersey_number', 'emergency_contact_name', 'emergency_contact_phone', 'skill_level',
            ]),
            UserRole::Coach => $this->coachProfile($user)?->only(['bio', 'credentials', 'certifications', 'is_public']),
            UserRole::Trainer => $user->trainerProfile?->only(['business_name', 'address', 'website', 'description']),
            UserRole::SuperAdmin => null,
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function updateRoleProfile(User $user, array $validated): void
    {
        match ($user->role) {
            UserRole::Player => $user->selfPlayerProfile?->update([
                'school' => $validated['school'] ?? null,
                'jersey_number' => $validated['jersey_number'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            ]),
            UserRole::Coach => CoachProfile::withoutGlobalScopes()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bio' => $validated['bio'] ?? null,
                    'credentials' => $validated['credentials'] ?? null,
                    'certifications' => $validated['certifications'] ?? null,
                    'is_public' => $validated['is_public'],
                ],
            ),
            UserRole::Trainer => TrainerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $validated['business_name'],
                    'address' => $validated['address'] ?? null,
                    'website' => $validated['website'] ?? null,
                    'description' => $validated['description'] ?? null,
                ],
            ),
            UserRole::SuperAdmin => null,
        };
    }

    /**
     * The coach's own profile; the tenant scope hides it until a trainer is attached.
     */
    private function coachProfile(User $user): ?CoachProfile
    {
        return CoachProfile::withoutGlobalScopes()->where('user_id', $user->id)->first();
    }
}
