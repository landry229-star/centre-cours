<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRecordRequest;
use App\Http\Resources\Api\V1\AttendanceResource;
use App\Http\Resources\Api\V1\AttendanceSessionResource;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
        ]);
        $query = $this->visibleAttendances($request->user())
            ->with(['session.classroom', 'session.subject', 'enrollment.student'])
            ->orderByDesc('attendance_session_id')
            ->orderBy('id');

        if (isset($filters['date'])) {
            $query->whereHas('session', fn (Builder $sessionQuery) => $sessionQuery
                ->whereDate('session_date', $filters['date']));
        }

        if (isset($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return AttendanceResource::collection($query->paginate(50));
    }

    public function sessions(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AttendanceSession::class);
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
        ]);
        $query = AttendanceSession::query()
            ->visibleTo($request->user())
            ->with(['classroom', 'subject', 'teacher'])
            ->orderByDesc('session_date')
            ->orderByDesc('starts_at')
            ->orderByDesc('id');

        if (isset($filters['date'])) {
            $query->whereDate('session_date', $filters['date']);
        }

        if (isset($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        return AttendanceSessionResource::collection($query->paginate(50));
    }

    public function store(
        AttendanceRecordRequest $request,
        AttendanceSession $attendanceSession,
        AttendanceService $attendance,
    ): JsonResponse {
        try {
            $summary = $attendance->record($attendanceSession, $request->validated('entries'), $request->user());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => ['summary' => $summary]]);
    }

    private function visibleAttendances(User $user): Builder
    {
        $query = Attendance::query();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isTeacher() && $user->teacher !== null) {
            return $query->whereHas('session', fn (Builder $sessionQuery) => $sessionQuery->visibleTo($user));
        }

        if ($user->isGuardian() && $user->guardian !== null) {
            return $query->whereHas('enrollment.student', fn (Builder $studentQuery) => $studentQuery
                ->where('guardian_id', $user->guardian->id));
        }

        if ($user->isStudent() && $user->student !== null) {
            return $query->whereHas('enrollment', fn (Builder $enrollmentQuery) => $enrollmentQuery
                ->where('student_id', $user->student->id));
        }

        return $query->whereRaw('1 = 0');
    }
}
