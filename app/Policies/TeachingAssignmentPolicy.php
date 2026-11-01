<?php

namespace App\Policies;

use App\Models\TeachingAssignment;
use App\Models\User;

class TeachingAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Un enseignant voit ses propres affectations (utile aux phases présences/notes). */
    public function view(User $user, TeachingAssignment $assignment): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $user->teacher !== null && $assignment->teacher_id === $user->teacher->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, TeachingAssignment $assignment): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, TeachingAssignment $assignment): bool
    {
        return $user->isAdmin();
    }
}
