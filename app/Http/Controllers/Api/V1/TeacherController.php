<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TeacherResource;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeacherController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return TeacherResource::collection(
            $this->visibleTeachers($request->user())
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('id')
                ->paginate(50),
        );
    }

    public function show(Request $request, int $teacher): TeacherResource
    {
        return new TeacherResource($this->visibleTeachers($request->user())->findOrFail($teacher));
    }

    private function visibleTeachers(User $user): Builder
    {
        $query = Teacher::query();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isTeacher() && $user->teacher !== null) {
            return $query->whereKey($user->teacher->id);
        }

        if ($user->isGuardian()) {
            return $query->whereHas('assignments.classroom.enrollments', fn (Builder $enrollmentQuery) => $enrollmentQuery
                ->active()
                ->whereHas('schoolYear', fn (Builder $yearQuery) => $yearQuery->where('is_active', true))
                ->whereHas('student.guardian', fn (Builder $guardianQuery) => $guardianQuery
                    ->where('user_id', $user->id)));
        }

        if ($user->isStudent() && $user->student !== null) {
            return $query->whereHas('assignments.classroom.enrollments', fn (Builder $enrollmentQuery) => $enrollmentQuery
                ->active()
                ->whereHas('schoolYear', fn (Builder $yearQuery) => $yearQuery->where('is_active', true))
                ->where('student_id', $user->student->id));
        }

        return $query->whereRaw('1 = 0');
    }
}
