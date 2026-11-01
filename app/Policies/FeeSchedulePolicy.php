<?php

namespace App\Policies;

use App\Models\FeeSchedule;
use App\Models\User;

class FeeSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, FeeSchedule $feeSchedule): bool
    {
        return $user->isAdmin();
    }
}
