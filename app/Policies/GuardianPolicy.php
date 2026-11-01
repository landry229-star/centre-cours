<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;

class GuardianPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $user->isAdmin()
            || ($user->isGuardian() && (int) $guardian->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Guardian $guardian): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Guardian $guardian): bool
    {
        return false;
    }
}
