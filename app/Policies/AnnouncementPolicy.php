<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isGuardian() && $user->guardian !== null);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin()
            || ($user->isGuardian() && $announcement->recipients()->whereKey($user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
