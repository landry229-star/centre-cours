<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;

class EvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $user->teacher !== null);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $user->teacher !== null);
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $user->teacher !== null && $evaluation->teacher_id === $user->teacher->id);
    }

    public function update(User $user, Evaluation $evaluation): bool
    {
        return $evaluation->published_at === null && $this->view($user, $evaluation);
    }

    public function publish(User $user, Evaluation $evaluation): bool
    {
        return $evaluation->published_at === null
            && ($user->isAdmin()
                || ($user->isTeacher() && $user->teacher !== null && $evaluation->teacher_id === $user->teacher->id));
    }
}
