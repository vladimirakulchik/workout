<?php

declare(strict_types=1);

namespace App\Actions\Coach;

use App\Enums\ShareLinkType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\CoachInvitationException;
use App\Mail\CoachWelcomeMail;
use App\Models\CoachProfile;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AcceptCoachInvitation
{
    /**
     * Attach a coach to the link's trainer and consume the link.
     *
     * Without `$existing` a new coach login is created from `$data`.
     *
     * @param  array<string, mixed>  $data  Validated JoinCoachRegistrationRequest data.
     *
     * @throws CoachInvitationException
     */
    public function handle(ShareLink $link, ?User $existing = null, array $data = []): User
    {
        $isNew = $existing === null;

        $user = DB::transaction(function () use ($link, $existing, $data): User {
            $locked = ShareLink::query()->withoutGlobalScopes()->lockForUpdate()->find($link->id);

            if ($locked === null || $locked->type !== ShareLinkType::Coach || ! $locked->isUsable()) {
                throw CoachInvitationException::unavailable();
            }

            if ($existing !== null && ! $existing->isCoach()) {
                throw CoachInvitationException::wrongRole();
            }

            if ($existing !== null && strcasecmp($existing->email, (string) $locked->target_email) !== 0) {
                throw CoachInvitationException::emailMismatch();
            }

            $user = $existing ?? $this->createUser($locked, $data);

            $profile = CoachProfile::query()->withoutGlobalScopes()->firstOrNew(['user_id' => $user->id]);

            if ($profile->trainer_id !== null && $profile->trainer_id !== $locked->trainer_id) {
                throw CoachInvitationException::activeUnderAnotherTrainer();
            }

            if ($profile->trainer_id === null) {
                $profile->fill(['trainer_id' => $locked->trainer_id, 'joined_at' => now()])->save();
            }

            $locked->forceFill(['uses_count' => $locked->uses_count + 1, 'is_active' => false])->save();

            return $user;
        });

        if ($isNew) {
            event(new Registered($user));
        }

        Mail::to($user)->send(new CoachWelcomeMail($user, $link->trainer));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createUser(ShareLink $link, array $data): User
    {
        if (User::query()->where('email', $link->target_email)->exists()) {
            throw CoachInvitationException::wrongRole();
        }

        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => mb_strtolower((string) $link->target_email),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::Coach,
            'status' => UserStatus::Active,
        ]);
    }
}
