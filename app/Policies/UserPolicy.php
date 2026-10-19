<?php

declare(strict_types=1);

namespace App\Policies;

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
        return $user->isSuperAdmin();
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->isNot($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->isNot($model);
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $model->canBeImpersonated();
    }
}
