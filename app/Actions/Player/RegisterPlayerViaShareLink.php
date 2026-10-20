<?php

declare(strict_types=1);

namespace App\Actions\Player;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\PlayerWelcomeMail;
use App\Models\PlayerProfile;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RegisterPlayerViaShareLink
{
    public function __construct(private readonly AssociateWithTrainer $associate) {}

    /**
     * Create a player login with its profile(s) and connect them to the link's trainer.
     *
     * With `is_parent` the login is a parent account that owns a child profile and, when `also_trains`
     * is set, a profile of their own. Otherwise the login owns a single self profile.
     *
     * @param  array<string, mixed>  $data  Validated JoinRegistrationRequest data.
     * @return array{user: User, profiles: Collection<int, PlayerProfile>}
     */
    public function handle(ShareLink $link, array $data): array
    {
        $isParent = (bool) ($data['is_parent'] ?? false);

        $result = DB::transaction(function () use ($link, $data, $isParent): array {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => UserRole::Player,
                'status' => UserStatus::Active,
            ]);

            $profiles = collect();

            if ($isParent) {
                $profiles->push(PlayerProfile::create([
                    'user_id' => null,
                    'parent_user_id' => $user->id,
                    'first_name' => $data['player_first_name'],
                    'last_name' => $data['player_last_name'],
                    'birth_date' => $data['birth_date'],
                    'gender' => $data['gender'],
                    'is_child' => true,
                    'emergency_contact_name' => $user->name,
                    'emergency_contact_phone' => $user->phone,
                ]));

                if ((bool) ($data['also_trains'] ?? false)) {
                    $profiles->push($this->selfProfile($user, [
                        'birth_date' => null,
                        'gender' => 'other',
                    ]));
                }
            } else {
                $profiles->push($this->selfProfile($user, [
                    'birth_date' => $data['birth_date'],
                    'gender' => $data['gender'],
                ]));
            }

            $this->associate->handle($link, $profiles);

            return ['user' => $user, 'profiles' => $profiles];
        });

        event(new Registered($result['user']));
        Mail::to($result['user'])->send(new PlayerWelcomeMail($result['user'], $link->trainer));

        return $result;
    }

    /**
     * @param  array{birth_date: mixed, gender: string}  $attributes
     */
    private function selfProfile(User $user, array $attributes): PlayerProfile
    {
        return PlayerProfile::create([
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'is_child' => false,
            'emergency_contact_name' => $user->name,
            'emergency_contact_phone' => $user->phone,
            ...$attributes,
        ]);
    }
}
