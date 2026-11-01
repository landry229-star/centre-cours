<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin()
            || ($user->isGuardian() && $user->guardian !== null)
            || ($user->isStudent() && $user->student !== null);
    }

    public function view(User $user, Grade $grade): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $grade->loadMissing(['evaluation', 'enrollment.student.guardian']);
        $student = $grade->enrollment?->student;

        if ($grade->evaluation?->published_at === null && ! $user->isTeacher()) {
            return false;
        }

        return match (true) {
            $user->isTeacher() => $user->teacher !== null && $grade->evaluation?->teacher_id === $user->teacher->id,
            $user->isGuardian() => $student?->guardian !== null && (int) $student->guardian->user_id === $user->id,
            $user->isStudent() => $student !== null && (int) $student->user_id === $user->id,
            default => false,
        };
    }

    public function correct(User $user, Grade $grade): bool
    {
        return $user->isAdmin()
            && $grade->evaluation?->published_at !== null;
    }
}
