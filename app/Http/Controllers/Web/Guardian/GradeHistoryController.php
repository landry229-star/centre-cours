<?php

namespace App\Http\Controllers\Web\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use App\Services\Grades\GradebookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GradeHistoryController extends Controller
{
    public function __invoke(Request $request, GradebookService $service): View
    {
        Gate::authorize('viewAny', Grade::class);
        $guardianId = $request->user()->guardian->id;

        $grades = Grade::query()
            ->whereNotNull('score')
            ->whereHas('evaluation', fn ($query) => $query->whereNotNull('published_at'))
            ->whereHas('enrollment.student', fn ($query) => $query
                ->withTrashed()
                ->where('guardian_id', $guardianId))
            ->with(['evaluation.classroom.schoolYear', 'evaluation.assignment.subject', 'evaluation.teacher', 'enrollment.student'])
            ->orderByDesc(Evaluation::select('evaluation_date')
                ->whereColumn('evaluations.id', 'grades.evaluation_id'))
            ->orderByDesc('grades.id')
            ->paginate(25);

        $students = Student::withTrashed()
            ->where('guardian_id', $guardianId)
            ->get();

        return view('grades.history', [
            'title' => 'Notes de mes enfants',
            'grades' => $grades,
            'summaries' => $service->summariesForStudents($students),
            'showStudent' => true,
        ]);
    }
}
