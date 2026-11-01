<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Services\Grades\ReportCardPdfService;
use App\Services\Grades\ReportCardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ReportCardController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Grade::class);

        $user = $request->user();
        $enrollments = Enrollment::query()
            ->with(['student', 'classroom.level', 'schoolYear'])
            ->when($user->isGuardian(), fn ($query) => $query->whereHas(
                'student',
                fn ($student) => $student->where('guardian_id', $user->guardian->id),
            ))
            ->when($user->isStudent(), fn ($query) => $query->where('student_id', $user->student->id))
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'grades',
                fn ($grades) => $grades
                    ->whereNotNull('score')
                    ->whereHas('evaluation', fn ($evaluation) => $evaluation->whereNotNull('published_at')),
            ))
            ->orderByDesc('school_year_id')
            ->orderByDesc('id')
            ->paginate(20);

        return view('report-cards.index', compact('enrollments'));
    }

    public function show(
        Request $request,
        Enrollment $enrollment,
        ReportCardService $reports,
        ReportCardPdfService $pdfs,
    ): View|Response {
        Gate::authorize('view', $enrollment);
        $enrollment->load(['student', 'classroom.level', 'schoolYear']);
        $user = $request->user();

        if (! $user->isAdmin() && ! $enrollment->grades()
            ->whereNotNull('score')
            ->whereHas('evaluation', fn ($query) => $query->whereNotNull('published_at'))
            ->exists()) {
            abort(404);
        }

        $periodType = $request->query('period_type');
        $periodNumber = $request->query('period_number');

        if ($periodType === null && $periodNumber === null) {
            return view('report-cards.select', [
                'enrollment' => $enrollment,
                'periods' => $reports->periods($enrollment->schoolYear),
            ]);
        }

        $allowedPeriodNumbers = match ($periodType) {
            'semester' => [1, 2],
            'annual' => [1],
            default => [1, 2, 3],
        };
        $selection = $request->validate([
            'period_type' => ['required', Rule::in(['trimester', 'semester', 'annual'])],
            'period_number' => ['required', 'integer', Rule::in($allowedPeriodNumbers)],
            'download' => ['sometimes', 'boolean'],
        ]);
        $report = $reports->make(
            $enrollment,
            $selection['period_type'],
            (int) $selection['period_number'],
            publishedOnly: ! $user->isAdmin(),
        );
        $pdf = $pdfs->make($report);
        $filename = $pdfs->filename($report);

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
