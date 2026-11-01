<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

/**
 * Qui peut lire UNE ligne de présence (utilisée par les espaces parent / élève, Phases 14-15).
 * Un parent ne voit jamais les absences des enfants d'un autre parent.
 */
class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGuardian() && $user->guardian !== null;
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $attendance->loadMissing(['session', 'enrollment.student.guardian']);
        $student = $attendance->enrollment?->student;

        return match (true) {
            $user->isTeacher() => $user->teacher !== null && $user->can('view', $attendance->session),
            $user->isGuardian() => $student?->guardian !== null && (int) $student->guardian->user_id === $user->id,
            $user->isStudent() => $student !== null && (int) $student->user_id === $user->id,
            default => false,
        };
    }
}
