<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SubjectResource;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SubjectResource::collection(
            $this->visibleSubjects($request->user())
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(50),
        );
    }

    public function show(Request $request, int $subject): SubjectResource
    {
        return new SubjectResource($this->visibleSubjects($request->user())->findOrFail($subject));
    }

    private function visibleSubjects(User $user): Builder
    {
        $query = Subject::query();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isTeacher() && $user->teacher !== null) {
            return $query->whereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery
                ->where('teacher_id', $user->teacher->id));
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
