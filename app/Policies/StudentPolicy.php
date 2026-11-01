<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        // Enseignants : leurs élèves seront ajoutés en Phase 8 (via teaching_assignments).
        return $user->isAdmin();
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Un parent ne voit QUE ses propres enfants.
        if ($user->isGuardian()) {
            return $student->guardian !== null
                && (int) $student->guardian->user_id === $user->id;
        }

        // Un élève ne voit que sa propre fiche.
        if ($user->isStudent()) {
            return (int) $student->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    // Aucune destruction définitive via l'application.
    public function forceDelete(User $user, Student $student): bool
    {
        return false;
    }
}
