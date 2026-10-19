<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReactivateUser
{
    /**
     * Restore access for a deactivated user. Deleted (anonymized) users can never be restored.
     */
    public function handle(User $admin, User $user): User
    {
        if ($user->status !== UserStatus::Inactive) {
            throw ValidationException::withMessages([
                'user' => $user->status === UserStatus::Deleted
                    ? __('Deleted users cannot be reactivated.')
                    : __('Only inactive users can be reactivated.'),
            ]);
        }

        DB::transaction(function () use ($admin, $user): void {
            $user->forceFill(['status' => UserStatus::Active])->save();

            activity()
                ->performedOn($user)
                ->causedBy($admin)
                ->event('user_reactivated')
                ->log('User reactivated');
        });

        return $user;
    }
}
