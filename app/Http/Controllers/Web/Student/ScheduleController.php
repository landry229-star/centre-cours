<?php

namespace App\Http\Controllers\Web\Student;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Schedule::class);
        $studentId = $request->user()->student->id;
        $schedules = Schedule::query()
            ->whereHas('classroom', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('schoolYear', fn ($year) => $year->where('is_active', true))
                ->whereHas('enrollments', fn ($enrollments) => $enrollments
                    ->active()
                    ->where('student_id', $studentId)))
            ->with(['classroom', 'assignment.subject', 'assignment.teacher', 'room'])
            ->orderedByWeekday()
            ->get();

        return view('schedules.list', [
            'title' => 'Mon emploi du temps',
            'schedules' => $schedules,
            'showStudent' => false,
            'showTeacher' => true,
        ]);
    }
}
