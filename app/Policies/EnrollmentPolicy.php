<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Admin ; le parent de CET élève ; l'élève lui-même. Jamais un autre parent. */
    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $student = $enrollment->student;

        if ($student === null) {
            return false;
        }

        if ($user->isGuardian()) {
            return $student->guardian !== null && (int) $student->guardian->user_id === $user->id;
        }

        if ($user->isStudent()) {
            return (int) $student->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Changement de classe et retrait. */
    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->isAdmin();
    }

    // Aucune suppression : un retrait ou un changement de classe garde la trace.
    public function delete(User $user, Enrollment $enrollment): bool
    {
        return false;
    }
}
