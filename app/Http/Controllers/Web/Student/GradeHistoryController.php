<?php

namespace App\Http\Controllers\Web\Student;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Services\Grades\GradebookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GradeHistoryController extends Controller
{
    public function __invoke(Request $request, GradebookService $service): View
    {
        Gate::authorize('viewAny', Grade::class);
        $student = $request->user()->student;

        $grades = Grade::query()
            ->whereNotNull('score')
            ->whereHas('evaluation', fn ($query) => $query->whereNotNull('published_at'))
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $student->id))
            ->with(['evaluation.classroom.schoolYear', 'evaluation.assignment.subject', 'evaluation.teacher'])
            ->orderByDesc(Evaluation::select('evaluation_date')
                ->whereColumn('evaluations.id', 'grades.evaluation_id'))
            ->orderByDesc('grades.id')
            ->paginate(25);

        return view('grades.history', [
            'title' => 'Mes notes',
            'grades' => $grades,
            'summaries' => $service->summariesForStudents(collect([$student])),
            'showStudent' => false,
        ]);
    }
}
