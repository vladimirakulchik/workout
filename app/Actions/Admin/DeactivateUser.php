<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeactivateUser
{
    /**
     * Block the user from logging in and end all of their sessions.
     */
    public function handle(User $admin, User $user): User
    {
        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'user' => __('Only active users can be deactivated.'),
            ]);
        }

        DB::transaction(function () use ($admin, $user): void {
            $user->forceFill(['status' => UserStatus::Inactive, 'remember_token' => null])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            activity()
                ->performedOn($user)
                ->causedBy($admin)
                ->event('user_deactivated')
                ->log('User deactivated');
        });

        return $user;
    }
}
