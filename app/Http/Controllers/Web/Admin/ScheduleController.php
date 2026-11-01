<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\DayOfWeek;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;
use App\Models\Classroom;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Services\Academics\ScheduleService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Schedule::class);
        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);
        $schoolYearId = $filters['school_year_id'] ?? SchoolYear::current()?->id;

        $schedules = Schedule::query()
            ->with(['classroom', 'assignment.subject', 'assignment.teacher', 'room'])
            ->whereHas('classroom', fn ($query) => $query->where('school_year_id', $schoolYearId ?? 0))
            ->when($filters['classroom_id'] ?? null, fn ($query, int $classroomId) => $query->where('classroom_id', $classroomId))
            ->when($filters['teacher_id'] ?? null, fn ($query, int $teacherId) => $query
                ->whereHas('assignment', fn ($assignmentQuery) => $assignmentQuery->where('teacher_id', $teacherId)))
            ->orderedByWeekday()
            ->paginate(30)
            ->withQueryString();

        return view('admin.schedules.index', [
            'schedules' => $schedules,
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->pluck('label', 'id')->all(),
            'classrooms' => Classroom::query()
                ->when($schoolYearId, fn ($query) => $query->where('school_year_id', $schoolYearId))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            'teachers' => Teacher::query()
                ->whereHas('assignments.classroom', fn ($query) => $query->where('school_year_id', $schoolYearId ?? 0))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->mapWithKeys(fn (Teacher $teacher): array => [$teacher->id => $teacher->full_name])
                ->all(),
            'schoolYearId' => $schoolYearId,
            'canManage' => $schoolYearId !== null && (int) $schoolYearId === SchoolYear::current()?->id,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Schedule::class);

        return view('admin.schedules.create', $this->formData());
    }

    public function store(ScheduleRequest $request, ScheduleService $service): RedirectResponse
    {
        try {
            $schedule = $service->create($request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.schedules.index', [
            'school_year_id' => $schedule->classroom->school_year_id,
        ])->with('success', "Séance ajoutée le {$schedule->day_of_week->label()}.");
    }

    public function edit(Schedule $schedule): View|RedirectResponse
    {
        Gate::authorize('update', $schedule);

        if (! $schedule->classroom->schoolYear->is_active) {
            return redirect()->route('admin.schedules.index', [
                'school_year_id' => $schedule->classroom->school_year_id,
            ])->with('error', 'Seul l’emploi du temps de l’année active peut être modifié.');
        }

        return view('admin.schedules.edit', $this->formData($schedule));
    }

    public function update(ScheduleRequest $request, Schedule $schedule, ScheduleService $service): RedirectResponse
    {
        try {
            $service->update($schedule, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.schedules.index')->with('success', 'Séance mise à jour.');
    }

    public function destroy(Schedule $schedule, ScheduleService $service): RedirectResponse
    {
        Gate::authorize('delete', $schedule);
        $yearId = $schedule->classroom->school_year_id;
        $service->delete($schedule);

        return redirect()->route('admin.schedules.index', ['school_year_id' => $yearId])
            ->with('success', 'Séance retirée de l’emploi du temps.');
    }

    /** @return array<string, mixed> */
    private function formData(?Schedule $schedule = null): array
    {
        $schoolYearId = $schedule?->classroom->school_year_id ?? SchoolYear::current()?->id;
        $assignments = TeachingAssignment::query()
            ->with(['classroom', 'subject', 'teacher'])
            ->whereHas('classroom', fn ($query) => $query
                ->where('status', 'active')
                ->where('school_year_id', $schoolYearId ?? 0))
            ->whereHas('teacher', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->sortBy(fn (TeachingAssignment $assignment) => $assignment->classroom->name.' '.$assignment->subject->name)
            ->mapWithKeys(fn (TeachingAssignment $assignment) => [
                $assignment->id => "{$assignment->classroom->name} — {$assignment->subject->name} ({$assignment->teacher->full_name})",
            ]);

        return [
            'schedule' => $schedule,
            'assignments' => $assignments,
            'rooms' => Room::query()->orderBy('name')->pluck('name', 'id')->all(),
            'days' => DayOfWeek::options(),
        ];
    }
}
