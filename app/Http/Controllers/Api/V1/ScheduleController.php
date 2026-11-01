<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ScheduleResource;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Schedule::class);
        $filters = $request->validate([
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
        ]);
        $query = $this->visibleSchedules($request->user())
            ->with(['classroom', 'assignment.subject', 'assignment.teacher', 'room'])
            ->orderedByWeekday();

        if (isset($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        return ScheduleResource::collection($query->paginate(50));
    }

    private function visibleSchedules(User $user): Builder
    {
        $query = Schedule::query();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isTeacher() && $user->teacher !== null) {
            return $query->whereHas('assignment', fn (Builder $assignmentQuery) => $assignmentQuery
                ->where('teacher_id', $user->teacher->id));
        }

        if ($user->isGuardian() && $user->guardian !== null) {
            return $query->whereHas('classroom', fn (Builder $classroomQuery) => $classroomQuery
                ->whereHas('schoolYear', fn (Builder $yearQuery) => $yearQuery->where('is_active', true))
                ->whereHas('enrollments', fn (Builder $enrollmentQuery) => $enrollmentQuery
                    ->active()
                    ->whereHas('student.guardian', fn (Builder $guardianQuery) => $guardianQuery
                        ->where('user_id', $user->id))));
        }

        if ($user->isStudent() && $user->student !== null) {
            return $query->whereHas('classroom', fn (Builder $classroomQuery) => $classroomQuery
                ->whereHas('schoolYear', fn (Builder $yearQuery) => $yearQuery->where('is_active', true))
                ->whereHas('enrollments', fn (Builder $enrollmentQuery) => $enrollmentQuery
                    ->active()
                    ->where('student_id', $user->student->id)));
        }

        return $query->whereRaw('1 = 0');
    }
}
