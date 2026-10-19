<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $model->status !== UserStatus::Deleted;
    }

    public function deactivate(User $user, User $model): bool
    {
        return $this->canManageLifecycle($user, $model) && $model->status === UserStatus::Active;
    }

    public function reactivate(User $user, User $model): bool
    {
        return $this->canManageLifecycle($user, $model) && $model->status === UserStatus::Inactive;
    }

    public function delete(User $user, User $model): bool
    {
        return $this->canManageLifecycle($user, $model) && $model->status !== UserStatus::Deleted;
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $model->canBeImpersonated();
    }

    /**
     * Super admins manage everyone except themselves and other super admins.
     */
    private function canManageLifecycle(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->isNot($model) && ! $model->isSuperAdmin();
    }
}
