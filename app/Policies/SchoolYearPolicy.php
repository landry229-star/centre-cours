<?php

namespace App\Policies;

use App\Models\SchoolYear;
use App\Models\User;

class SchoolYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SchoolYear $year): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SchoolYear $year): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SchoolYear $year): bool
    {
        return $user->isAdmin();
    }

    public function activate(User $user, SchoolYear $year): bool
    {
        return $user->isAdmin();
    }
}
