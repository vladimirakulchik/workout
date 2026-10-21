<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Enums\UserRole;
use App\Models\CoachProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateUserProfile
{
    public function __construct(private readonly StoreProfilePhoto $storePhoto) {}

    /**
     * Update the user's base fields, the role-specific profile and optionally the photo. Email and role never change.
     *
     * @param  array<string, mixed>  $validated
     */
    public function handle(User $user, array $validated, ?UploadedFile $photo = null): void
    {
        DB::transaction(function () use ($user, $validated, $photo): void {
            if (Gate::forUser($user)->allows('child.restricted')) {
                if ($photo !== null) {
                    $this->storePhoto->handle($user, $photo);
                }

                return;
            }

            $user->fill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? null,
            ])->save();

            $this->updateRoleProfile($user, $validated);

            if ($photo !== null) {
                $this->storePhoto->handle($user, $photo);
            }
        });
    }

    /**
     * Role-specific profile fields shaped for the profile forms.
     *
     * @return array<string, mixed>|null
     */
    public function roleProfileProps(User $user): ?array
    {
        return match ($user->role) {
            UserRole::Player => $user->selfPlayerProfile?->only([
                'school', 'jersey_number', 'emergency_contact_name', 'emergency_contact_phone', 'skill_level',
            ]),
            UserRole::Coach => CoachProfile::withoutGlobalScopes()->where('user_id', $user->id)->first()
                ?->only(['bio', 'credentials', 'certifications', 'is_public']),
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
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
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
}
