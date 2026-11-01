<?php

namespace App\Http\Controllers\Web\Guardian;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Services\Billing\FeeStatementService;
use App\Services\Grades\GradebookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        FeeStatementService $feeStatements,
        GradebookService $gradebook,
    ): View {
        $schoolYear = SchoolYear::current();
        $guardianId = $request->user()->guardian?->id;
        $children = Student::query()
            ->when(
                $guardianId !== null,
                fn ($query) => $query->where('guardian_id', $guardianId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with(['enrollments' => fn ($query) => $query
                ->active()
                ->when($schoolYear, fn ($enrollment) => $enrollment->where('school_year_id', $schoolYear->id))
                ->with('classroom')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $classroomIds = $children->flatMap(fn (Student $student) => $student->enrollments->pluck('classroom_id'))
            ->unique()
            ->values();
        $summaries = $gradebook->summariesForStudents($children)
            ->when($schoolYear, fn ($rows) => $rows->where('school_year.id', $schoolYear->id)->values());
        $financialStatements = $schoolYear === null
            ? collect()
            : $feeStatements->forStudents($schoolYear, $children->modelKeys());

        return view('dashboards.guardian', [
            'schoolYear' => $schoolYear,
            'children' => $children,
            'summaries' => $summaries,
            'financialStatements' => $financialStatements,
            'absenceCount' => Attendance::query()
                ->where('status', AttendanceStatus::Absent)
                ->whereHas('enrollment', fn ($query) => $query->whereIn('student_id', $children->modelKeys()))
                ->when($schoolYear, fn ($query) => $query->whereHas(
                    'session',
                    fn ($session) => $session->whereBetween('session_date', [
                        $schoolYear->start_date->toDateString(),
                        $schoolYear->end_date->toDateString(),
                    ]),
                ))
                ->count(),
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
