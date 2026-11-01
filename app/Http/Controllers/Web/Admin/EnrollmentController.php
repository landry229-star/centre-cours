<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollmentRequest;
use App\Http\Requests\WithdrawEnrollmentRequest;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Services\Academics\EnrollmentService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Enrollment::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'school_year_id' => ['nullable', 'integer'],
            'classroom_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
        ]);

        // Par défaut : l'année active. Choisir « Toutes les années » envoie une valeur vide.
        $yearId = $request->has('school_year_id')
            ? ($filters['school_year_id'] ?? null)
            : SchoolYear::current()?->id;

        $status = EnrollmentStatus::tryFrom($filters['status'] ?? '');
        $search = $filters['search'] ?? null;

        $enrollments = Enrollment::query()
            ->with(['student', 'classroom.level', 'schoolYear'])
            ->when($yearId, fn ($q, $id) => $q->where('school_year_id', $id))
            ->when($filters['classroom_id'] ?? null, fn ($q, $id) => $q->where('classroom_id', $id))
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($search, fn ($q, $term) => $q->whereHas('student', fn ($s) => $s->search($term)))
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.enrollments.index', [
            'enrollments' => $enrollments,
            'yearId' => $yearId,
            'schoolYears' => SchoolYear::orderByDesc('start_date')->pluck('label', 'id')->all(),
            'classrooms' => Classroom::query()
                ->when($yearId, fn ($q, $id) => $q->where('school_year_id', $id))
                ->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Student $student): View
    {
        Gate::authorize('create', Enrollment::class);

        return view('admin.enrollments.create', [
            'student' => $student,
            'classroomsByYear' => $this->openClassrooms()->groupBy(fn ($c) => $c->schoolYear->label),
            // Années où l'élève est déjà inscrit : ces classes seront désactivées dans la liste.
            'busyYearIds' => Enrollment::active()->where('student_id', $student->id)->pluck('school_year_id')->all(),
        ]);
    }

    public function store(EnrollmentRequest $request, Student $student, EnrollmentService $service): RedirectResponse
    {
        $classroom = Classroom::findOrFail($request->validated('classroom_id'));

        try {
            $service->enroll($student, $classroom, $request->date('date'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['classroom_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', "{$student->full_name} est inscrit(e) en {$classroom->name}.");
    }

    public function transferForm(Enrollment $enrollment): View|RedirectResponse
    {
        Gate::authorize('update', $enrollment);

        if (! $enrollment->isActive()) {
            return redirect()
                ->route('admin.students.show', $enrollment->student_id)
                ->with('error', "Cette inscription n'est plus en cours.");
        }

        $enrollment->load(['student', 'classroom.level', 'schoolYear']);

        return view('admin.enrollments.transfer', [
            'enrollment' => $enrollment,
            'classrooms' => $this->openClassrooms()
                ->where('school_year_id', $enrollment->school_year_id)
                ->where('id', '!=', $enrollment->classroom_id),
        ]);
    }

    public function transfer(EnrollmentRequest $request, Enrollment $enrollment, EnrollmentService $service): RedirectResponse
    {
        $target = Classroom::findOrFail($request->validated('classroom_id'));

        try {
            $service->transfer($enrollment, $target, $request->date('date'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['classroom_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.students.show', $enrollment->student_id)
            ->with('success', "Changement de classe enregistré : {$target->name}.");
    }

    public function withdraw(WithdrawEnrollmentRequest $request, Enrollment $enrollment, EnrollmentService $service): RedirectResponse
    {
        try {
            $service->withdraw($enrollment, $request->date('date'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.students.show', $enrollment->student_id)
            ->with('success', 'Retrait enregistré. L\'historique est conservé.');
    }

    /** Classes ouvertes, avec leur effectif actuel (pour afficher « 12/40 places »). */
    private function openClassrooms()
    {
        return Classroom::query()
            ->where('status', 'active')
            ->with(['level', 'schoolYear'])
            ->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('school_year_id')
            ->orderBy('level_id')
            ->orderBy('name')
            ->get();
    }
}
