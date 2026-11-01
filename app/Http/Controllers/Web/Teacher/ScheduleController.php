<?php

namespace App\Http\Controllers\Web\Teacher;

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
        $schedules = Schedule::query()
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $request->user()->teacher->id))
            ->whereHas('classroom', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('schoolYear', fn ($year) => $year->where('is_active', true)))
            ->with(['classroom', 'assignment.subject', 'room'])
            ->orderedByWeekday()
            ->get();

        return view('schedules.list', [
            'title' => 'Mon emploi du temps',
            'schedules' => $schedules,
            'showStudent' => false,
            'showTeacher' => false,
        ]);
    }
}
