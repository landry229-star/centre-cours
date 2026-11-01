<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return StudentResource::collection(
            $this->visibleStudents($request->user())
                ->with('guardian')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('id')
                ->paginate(50),
        );
    }

    public function show(Request $request, int $student): StudentResource
    {
        $student = $this->visibleStudents($request->user())->with('guardian')->findOrFail($student);

        return new StudentResource($student);
    }

    private function visibleStudents(User $user): Builder
    {
        $query = Student::query();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isGuardian() && $user->guardian !== null) {
            return $query->where('guardian_id', $user->guardian->id);
        }

        if ($user->isStudent() && $user->student !== null) {
            return $query->whereKey($user->student->id);
        }

        if ($user->isTeacher() && $user->teacher !== null) {
            return $query->whereHas('enrollments.classroom', function (Builder $classroomQuery) use ($user) {
                $classroomQuery->whereHas('schoolYear', fn (Builder $yearQuery) => $yearQuery->where('is_active', true))
                    ->where(function (Builder $classroomQuery) use ($user) {
                        $classroomQuery->where('head_teacher_id', $user->teacher->id)
                            ->orWhereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery
                                ->where('teacher_id', $user->teacher->id));
                    });
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
