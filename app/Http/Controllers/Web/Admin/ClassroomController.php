<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\ClassroomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassroomRequest;
use App\Models\Classroom;
use App\Models\Level;
use App\Models\Room;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\Academics\ClassroomService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Classroom::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'school_year_id' => ['nullable', 'integer'],
            'level_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
        ]);

        $status = ClassroomStatus::tryFrom($filters['status'] ?? '');

        $classrooms = Classroom::query()
            ->with(['level', 'schoolYear', 'room', 'headTeacher'])
            ->search($filters['search'] ?? null)
            ->when($filters['school_year_id'] ?? null, fn ($q, $id) => $q->where('school_year_id', $id))
            ->when($filters['level_id'] ?? null, fn ($q, $id) => $q->where('level_id', $id))
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->orderByDesc('school_year_id')
            ->orderBy('level_id')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.classrooms.index', [
            'classrooms' => $classrooms,
            'schoolYears' => SchoolYear::orderByDesc('start_date')->pluck('label', 'id')->all(),
            'levels' => Level::orderBy('position')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Classroom::class);

        return view('admin.classrooms.create', $this->formData());
    }

    public function store(ClassroomRequest $request, ClassroomService $service): RedirectResponse
    {
        $classroom = $service->create($request->validated());

        return redirect()
            ->route('admin.classrooms.show', $classroom)
            ->with('success', "Classe {$classroom->name} créée. Ajoutez maintenant les matières et enseignants.");
    }

    public function show(Classroom $classroom): View
    {
        Gate::authorize('view', $classroom);

        $classroom->load(['level', 'schoolYear', 'room', 'headTeacher', 'assignments.subject', 'assignments.teacher']);

        $assignedSubjectIds = $classroom->assignments->pluck('subject_id');

        return view('admin.classrooms.show', [
            'classroom' => $classroom,
            // Matières pas encore enseignées dans cette classe
            'availableSubjects' => Subject::whereNotIn('id', $assignedSubjectIds)->orderBy('name')->get(),
            'activeTeachers' => Teacher::where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get(),
            'enrollments' => $classroom->enrollments()->active()->with('student')->get()
                ->sortBy(fn ($e) => $e->student->full_name)->values(),
        ]);
    }

    public function edit(Classroom $classroom): View
    {
        Gate::authorize('update', $classroom);

        return view('admin.classrooms.edit', $this->formData() + ['classroom' => $classroom]);
    }

    public function update(ClassroomRequest $request, Classroom $classroom, ClassroomService $service): RedirectResponse
    {
        try {
            $service->update($classroom, $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.classrooms.show', $classroom)
            ->with('success', 'Classe mise à jour.');
    }

    public function destroy(Classroom $classroom, ClassroomService $service): RedirectResponse
    {
        Gate::authorize('delete', $classroom);

        try {
            $service->delete($classroom);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.classrooms.index')
            ->with('success', "Classe {$classroom->name} supprimée.");
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'schoolYears' => SchoolYear::orderByDesc('start_date')->pluck('label', 'id')->all(),
            'activeYearId' => SchoolYear::current()?->id,
            'levels' => Level::orderBy('position')->pluck('name', 'id')->all(),
            'rooms' => Room::orderBy('name')->pluck('name', 'id')->all(),
            'teachers' => Teacher::where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get()
                ->mapWithKeys(fn ($t) => [$t->id => $t->full_name])->all(),
        ];
    }
}
