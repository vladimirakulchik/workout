<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserStatus;
use App\Models\CoachProfile;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserDeletionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    /**
     * Irreversibly anonymize the user, keeping the row so history references stay valid.
     * The original data is kept encrypted in the deletion log.
     */
    public function handle(User $admin, User $user, string $reason): User
    {
        if ($user->status === UserStatus::Deleted) {
            throw ValidationException::withMessages([
                'user' => __('This user has already been deleted.'),
            ]);
        }

        $user->loadMissing(['trainerProfile', 'coachProfile', 'playerProfiles']);

        $filesToDelete = array_values(array_filter([
            $user->photo_path,
            $user->trainerProfile?->logo_path,
            ...$user->playerProfiles->pluck('photo_path')->all(),
        ]));

        DB::transaction(function () use ($admin, $user, $reason): void {
            UserDeletionLog::create([
                'original_user_id' => $user->id,
                'original_email' => $user->email,
                'deleted_by' => $admin->id,
                'reason' => $reason,
                'original_data' => $this->snapshot($user),
                'deleted_at' => now(),
            ]);

            $this->anonymizeProfiles($user);

            $user->forceFill([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'email' => 'deleted_'.$user->id.'@example.com',
                'phone' => null,
                'photo_path' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'status' => UserStatus::Deleted,
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            ShareLink::query()
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->where(fn ($query) => $query->where('trainer_id', $user->id)->orWhere('created_by', $user->id))
                ->update(['is_active' => false]);

            activity()
                ->performedOn($user)
                ->causedBy($admin)
                ->event('user_deleted')
                ->withProperties(['reason' => $reason])
                ->log('User deleted and anonymized');
        });

        Storage::disk('public')->delete($filesToDelete);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            'user' => $user->only(['first_name', 'last_name', 'email', 'phone', 'photo_path', 'role', 'status', 'created_at']),
            'trainer_profile' => $user->trainerProfile?->toArray(),
            'coach_profile' => $user->coachProfile?->toArray(),
            'player_profiles' => $user->playerProfiles->toArray(),
        ];
    }

    private function anonymizeProfiles(User $user): void
    {
        TrainerProfile::where('user_id', $user->id)->update([
            'business_name' => 'Deleted',
            'address' => null,
            'website' => null,
            'description' => null,
            'logo_path' => null,
            'brand_color' => null,
        ]);

        CoachProfile::where('user_id', $user->id)->update([
            'bio' => null,
            'credentials' => null,
            'certifications' => null,
            'is_public' => false,
        ]);

        PlayerProfile::where('user_id', $user->id)->update([
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'birth_date' => null,
            'school' => null,
            'jersey_number' => null,
            'photo_path' => null,
            'emergency_contact_name' => '',
            'emergency_contact_phone' => null,
        ]);
    }
}
