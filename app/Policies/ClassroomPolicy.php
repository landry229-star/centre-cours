<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Un enseignant ne voit QUE les classes où il enseigne ou dont il est professeur principal. */
    public function view(User $user, Classroom $classroom): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            $teacher = $user->teacher;

            return $teacher !== null && $classroom->isTaughtBy($teacher);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Classroom $classroom): bool
    {
        return false;
    }
}
