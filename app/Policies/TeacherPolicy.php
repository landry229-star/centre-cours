<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && (int) $teacher->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Teacher $teacher): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Teacher $teacher): bool
    {
        return false;
    }
}
