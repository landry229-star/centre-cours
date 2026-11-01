<?php

namespace App\Http\Controllers\Web;

use App\Enums\EvaluationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\EvaluationRequest;
use App\Http\Requests\GradeCorrectionRequest;
use App\Http\Requests\GradeEntriesRequest;
use App\Models\Classroom;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolYear;
use App\Models\TeachingAssignment;
use App\Services\Grades\GradebookService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EvaluationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Evaluation::class);
        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
        ]);
        $schoolYearId = $filters['school_year_id'] ?? SchoolYear::current()?->id;

        $evaluations = Evaluation::query()
            ->with(['classroom', 'assignment.subject', 'teacher'])
            ->whereHas('classroom', fn ($query) => $query->where('school_year_id', $schoolYearId ?? 0))
            ->when($filters['classroom_id'] ?? null, fn ($query, int $classroomId) => $query->where('classroom_id', $classroomId))
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('teacher_id', $request->user()->teacher?->id ?? 0))
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('grades.index', [
            'evaluations' => $evaluations,
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->pluck('label', 'id')->all(),
            'classrooms' => Classroom::query()
                ->when($schoolYearId, fn ($query) => $query->where('school_year_id', $schoolYearId))
                ->when(! $request->user()->isAdmin(), fn ($query) => $query->whereHas('assignments', fn ($assignments) => $assignments
                    ->where('teacher_id', $request->user()->teacher?->id ?? 0)))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            'schoolYearId' => $schoolYearId,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Evaluation::class);
        $schoolYearId = SchoolYear::current()?->id;
        $assignments = TeachingAssignment::query()
            ->with(['classroom', 'subject', 'teacher'])
            ->whereHas('classroom', fn ($query) => $query
                ->where('status', 'active')
                ->where('school_year_id', $schoolYearId ?? 0))
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('teacher_id', $request->user()->teacher?->id ?? 0))
            ->get()
            ->sortBy(fn (TeachingAssignment $assignment) => $assignment->classroom->name.' '.$assignment->subject->name)
            ->mapWithKeys(fn (TeachingAssignment $assignment) => [
                $assignment->id => "{$assignment->classroom->name} — {$assignment->subject->name} ({$assignment->teacher->full_name})",
            ]);

        return view('grades.create', [
            'assignments' => $assignments,
            'types' => EvaluationType::options(),
        ]);
    }

    public function store(EvaluationRequest $request, GradebookService $service): RedirectResponse
    {
        $assignment = TeachingAssignment::query()->findOrFail($request->integer('assignment_id'));

        try {
            $evaluation = $service->createEvaluation($assignment, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('grades.show', $evaluation)
            ->with('success', 'Évaluation créée. Vous pouvez maintenant saisir les notes sur 20.');
    }

    public function show(Evaluation $evaluation, GradebookService $service): View
    {
        Gate::authorize('view', $evaluation);
        $evaluation->load(['classroom.schoolYear', 'assignment.subject', 'teacher', 'publisher']);

        return view('grades.show', [
            'evaluation' => $evaluation,
            'roster' => $service->roster($evaluation),
            'grades' => $evaluation->grades()->with('corrections')->get()->keyBy('enrollment_id'),
            'ranking' => $service->ranking($evaluation->classroom, $evaluation->classroom->schoolYear),
        ]);
    }

    public function update(GradeEntriesRequest $request, Evaluation $evaluation, GradebookService $service): RedirectResponse
    {
        try {
            $service->recordGrades($evaluation, $request->validated('entries'), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('grades.show', $evaluation)
            ->with('success', 'Les notes ont été enregistrées.');
    }

    public function publish(Request $request, Evaluation $evaluation, GradebookService $service): RedirectResponse
    {
        Gate::authorize('publish', $evaluation);

        try {
            $service->publishEvaluation($evaluation, $request->user());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Les résultats de cette évaluation sont maintenant publiés.');
    }

    public function correctGrade(GradeCorrectionRequest $request, Grade $grade, GradebookService $service): RedirectResponse
    {
        Gate::authorize('correct', $grade);

        try {
            $service->correctPublishedGrade(
                $grade,
                $request->validated('score'),
                $request->validated('reason'),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('grades.show', $grade->evaluation_id)
            ->with('success', 'La note a été corrigée et le changement a été journalisé.');
    }
}
