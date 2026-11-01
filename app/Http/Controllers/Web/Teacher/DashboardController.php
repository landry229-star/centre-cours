<?php

namespace App\Http\Controllers\Web\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\TeachingAssignment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $teacher = $request->user()->teacher;
        $schoolYear = SchoolYear::current();
        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $teacher?->id)
            ->whereHas('classroom', fn ($query) => $query
                ->where('status', 'active')
                ->when($schoolYear, fn ($year) => $year->where('school_year_id', $schoolYear->id)))
            ->with(['classroom', 'subject'])
            ->orderBy('classroom_id')
            ->orderBy('subject_id')
            ->get();
        $classroomIds = $assignments->pluck('classroom_id')->unique()->values();
        $today = strtolower(now()->englishDayOfWeek);

        return view('dashboards.teacher', [
            'schoolYear' => $schoolYear,
            'classCount' => $classroomIds->count(),
            'studentCount' => Enrollment::query()
                ->active()
                ->whereIn('classroom_id', $classroomIds)
                ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
                ->distinct('student_id')
                ->count('student_id'),
            'assignments' => $assignments,
            'todaySchedules' => Schedule::query()
                ->whereIn('classroom_id', $classroomIds)
                ->where('day_of_week', $today)
                ->whereHas('classroom', fn ($query) => $query
                    ->where('status', 'active')
                    ->when($schoolYear, fn ($year) => $year->where('school_year_id', $schoolYear->id)))
                ->with(['classroom', 'assignment.subject', 'room'])
                ->orderBy('starts_at')
                ->get(),
            'todaySessions' => AttendanceSession::query()
                ->visibleTo($request->user())
                ->whereDate('session_date', today())
                ->with(['classroom', 'subject'])
                ->orderBy('starts_at')
                ->limit(5)
                ->get(),
            'recentGrades' => Grade::query()
                ->where('entered_by', $request->user()->id)
                ->whereNotNull('score')
                ->whereHas('evaluation', fn ($query) => $query
                    ->where('teacher_id', $teacher?->id)
                    ->when($schoolYear, fn ($evaluation) => $evaluation->whereHas(
                        'classroom',
                        fn ($classroom) => $classroom->where('school_year_id', $schoolYear->id),
                    )))
                ->with(['evaluation.assignment.subject', 'evaluation.classroom', 'enrollment.student'])
                ->latest('updated_at')
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }
}
