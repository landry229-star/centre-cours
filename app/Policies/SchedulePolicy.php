<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $user->teacher !== null)
            || ($user->isGuardian() && $user->guardian !== null)
            || ($user->isStudent() && $user->student !== null);
    }

    public function view(User $user, Schedule $schedule): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $schedule->loadMissing(['assignment', 'classroom.schoolYear']);

        return match (true) {
            $user->isTeacher() => $user->teacher !== null && $schedule->assignment?->teacher_id === $user->teacher->id,
            $user->isGuardian() => $user->guardian !== null
                && $schedule->classroom?->schoolYear?->is_active === true
                && Enrollment::query()->active()
                    ->where('classroom_id', $schedule->classroom_id)
                    ->whereHas('student.guardian', fn ($query) => $query->where('user_id', $user->id))
                    ->exists(),
            $user->isStudent() => $user->student !== null
                && $schedule->classroom?->schoolYear?->is_active === true
                && Enrollment::query()->active()
                    ->where('classroom_id', $schedule->classroom_id)
                    ->where('student_id', $user->student->id)
                    ->exists(),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $user->isAdmin();
    }
}
