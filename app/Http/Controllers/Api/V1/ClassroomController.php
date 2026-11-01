<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClassroomResource;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassroomController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
        ]);
        $query = $this->visibleClassrooms($request->user())
            ->with(['schoolYear', 'level', 'room'])
            ->orderBy('name')
            ->orderBy('id');

        if (isset($filters['school_year_id'])) {
            $query->where('school_year_id', $filters['school_year_id']);
        } else {
            $query->whereHas('schoolYear', fn (Builder $yearQuery) => $yearQuery->where('is_active', true));
        }

        return ClassroomResource::collection($query->paginate(50));
    }

    public function show(Request $request, int $classroom): ClassroomResource
    {
        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
        ]);
        $query = $this->visibleClassrooms($request->user())->with(['schoolYear', 'level', 'room']);

        if (isset($filters['school_year_id'])) {
            $query->where('school_year_id', $filters['school_year_id']);
        } else {
            $query->whereHas('schoolYear', fn (Builder $yearQuery) => $yearQuery->where('is_active', true));
        }

        return new ClassroomResource($query->findOrFail($classroom));
    }

    private function visibleClassrooms(User $user): Builder
    {
        $query = Classroom::query();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isTeacher() && $user->teacher !== null) {
            return $query->where(function (Builder $classroomQuery) use ($user) {
                $classroomQuery->where('head_teacher_id', $user->teacher->id)
                    ->orWhereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery
                        ->where('teacher_id', $user->teacher->id));
            });
        }

        if ($user->isGuardian() && $user->guardian !== null) {
            return $query->whereHas('enrollments', fn (Builder $enrollmentQuery) => $enrollmentQuery
                ->active()
                ->whereHas('student', fn (Builder $studentQuery) => $studentQuery
                    ->where('guardian_id', $user->guardian->id)));
        }

        if ($user->isStudent() && $user->student !== null) {
            return $query->whereHas('enrollments', fn (Builder $enrollmentQuery) => $enrollmentQuery
                ->active()
                ->where('student_id', $user->student->id));
        }

        return $query->whereRaw('1 = 0');
    }
}
