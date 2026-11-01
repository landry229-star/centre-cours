<?php

namespace App\Http\Controllers\Web\Student;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Services\Grades\GradebookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GradebookService $gradebook): View
    {
        $student = $request->user()->student;
        $schoolYear = SchoolYear::current();
        $enrollments = $student?->enrollments()
            ->active()
            ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->with('classroom')
            ->get() ?? collect();
        $enrollmentIds = $enrollments->pluck('id');
        $classroomIds = $enrollments->pluck('classroom_id')->unique()->values();

        return view('dashboards.student', [
            'schoolYear' => $schoolYear,
            'enrollments' => $enrollments,
            'summaries' => $student === null
                ? collect()
                : $gradebook->summariesForStudent($student)
                    ->when($schoolYear, fn ($rows) => $rows->where('school_year.id', $schoolYear->id)->values()),
            'absenceCount' => Attendance::query()
                ->where('status', AttendanceStatus::Absent)
                ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student?->id))
                ->when($schoolYear, fn ($query) => $query->whereHas(
                    'session',
                    fn ($session) => $session->whereBetween('session_date', [
                        $schoolYear->start_date->toDateString(),
                        $schoolYear->end_date->toDateString(),
                    ]),
                ))
                ->count(),
            'recentGrades' => Grade::query()
                ->whereIn('enrollment_id', $enrollmentIds)
                ->whereNotNull('score')
                ->whereHas('evaluation', fn ($query) => $query->whereNotNull('published_at'))
                ->with(['evaluation.assignment.subject', 'evaluation.classroom'])
                ->orderByDesc(Evaluation::select('evaluation_date')
                    ->whereColumn('evaluations.id', 'grades.evaluation_id'))
                ->orderByDesc('grades.id')
                ->limit(5)
                ->get(),
            'todaySchedules' => Schedule::query()
                ->whereIn('classroom_id', $classroomIds)
                ->where('day_of_week', strtolower(now()->englishDayOfWeek))
                ->whereHas('classroom', fn ($query) => $query
                    ->where('status', 'active')
                    ->when($schoolYear, fn ($year) => $year->where('school_year_id', $schoolYear->id)))
                ->with(['classroom', 'assignment.subject', 'assignment.teacher', 'room'])
                ->orderBy('starts_at')
                ->get(),
        ]);
    }
}
