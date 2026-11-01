<?php

namespace App\Policies;

use App\Enums\AttendanceSessionType;
use App\Models\AttendanceSession;
use App\Models\TeachingAssignment;
use App\Models\User;

class AttendanceSessionPolicy
{
    /** Liste : admin et enseignants (chacun ne voit que ses séances, voir AttendanceSession::scopeVisibleTo). */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $user->teacher !== null);
    }

    /** Ouvrir une séance pour une affectation : l'admin, ou l'enseignant À QUI elle est affectée. */
    public function openFor(User $user, TeachingAssignment $assignment): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $user->teacher !== null && $assignment->teacher_id === $user->teacher->id);
    }

    /** Admin, enseignant de la séance, ou enseignant affecté à la classe (et à la matière pour un cours). */
    public function view(User $user, AttendanceSession $session): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $teacher = $user->isTeacher() ? $user->teacher : null;

        if ($teacher === null) {
            return false;
        }

        return $session->teacher_id === $teacher->id
            || TeachingAssignment::query()
                ->where('classroom_id', $session->classroom_id)
                ->when(
                    $session->session_type === AttendanceSessionType::Lesson,
                    fn ($query) => $query->where('subject_id', $session->subject_id),
                )
                ->where('teacher_id', $teacher->id)
                ->exists();
    }

    /** Corriger la feuille ou scanner : mêmes droits que la consultation. */
    public function update(User $user, AttendanceSession $session): bool
    {
        return $this->view($user, $session);
    }

    // Jamais de suppression : une séance passée est une trace.
    public function delete(User $user, AttendanceSession $session): bool
    {
        return false;
    }
}
