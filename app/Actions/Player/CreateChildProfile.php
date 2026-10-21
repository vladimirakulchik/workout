<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Actions\Profile\StoreProfilePhoto;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PlayerProfile;
use App\Models\TrainerPlayerAssociation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateChildProfile
{
    public function __construct(private readonly StoreProfilePhoto $storePhoto) {}

    /**
     * Existing children of the parent with a similar first name and the same birth year.
     *
     * @return Collection<int, PlayerProfile>
     */
    public function findDuplicates(User $parent, string $firstName, string $birthDate): Collection
    {
        $year = Carbon::parse($birthDate)->year;
        $needle = mb_strtolower(trim($firstName));

        return $parent->childProfiles()
            ->whereYear('birth_date', $year)
            ->get()
            ->filter(function (PlayerProfile $child) use ($needle): bool {
                $existing = mb_strtolower($child->first_name);

                return $existing === $needle
                    || str_starts_with($existing, $needle)
                    || str_starts_with($needle, $existing)
                    || levenshtein($existing, $needle) <= 2;
            })
            ->values();
    }

    /**
     * Create a child profile owned by the parent, optionally with its own login, and connect it to
     * the chosen trainers (none = unassociated).
     *
     * @param  array<string, mixed>  $data  Validated StoreChildProfileRequest data.
     */
    public function handle(User $parent, array $data): PlayerProfile
    {
        $login = null;
        $photo = $data['photo'] ?? null;

        $profile = DB::transaction(function () use ($parent, $data, &$login): PlayerProfile {
            if ((bool) ($data['has_login'] ?? false)) {
                $login = User::create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $parent->phone,
                    'password' => $data['password'],
                    'role' => UserRole::Player,
                    'status' => UserStatus::Active,
                ]);
            }

            $profile = PlayerProfile::create([
                'user_id' => $login?->id,
                'parent_user_id' => $parent->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'school' => $data['school'] ?? null,
                'is_child' => true,
                'emergency_contact_name' => $parent->name,
                'emergency_contact_phone' => $parent->phone,
                'allow_token_spending' => (bool) ($data['allow_token_spending'] ?? false),
            ]);

            foreach (array_unique($data['trainer_ids'] ?? []) as $trainerId) {
                TrainerPlayerAssociation::query()->withoutGlobalScopes()->create([
                    'trainer_id' => $trainerId,
                    'player_profile_id' => $profile->id,
                    'connected_at' => now(),
                    'status' => TrainerPlayerAssociation::STATUS_ACTIVE,
                ]);
            }

            return $profile;
        });

        if ($photo instanceof UploadedFile) {
            $this->storePhoto->handle($profile, $photo);
        }

        $login?->sendEmailVerificationNotification();

        return $profile;
    }
}
