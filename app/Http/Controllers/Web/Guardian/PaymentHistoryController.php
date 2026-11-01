<?php

namespace App\Http\Controllers\Web\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Services\Billing\FeeStatementService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentHistoryController extends Controller
{
    public function __invoke(Request $request, FeeStatementService $feeStatements): View
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer'],
        ]);
        $guardianId = $request->user()->guardian->id;
        $studentIds = Student::withTrashed()
            ->where('guardian_id', $guardianId)
            ->pluck('id')
            ->all();
        $schoolYears = SchoolYear::query()
            ->whereHas('enrollments', fn (Builder $query) => $query->whereIn('student_id', $studentIds))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        if (isset($filters['school_year_id']) && ! $schoolYears->contains('id', (int) $filters['school_year_id'])) {
            abort(404);
        }

        $schoolYear = isset($filters['school_year_id'])
            ? $schoolYears->firstWhere('id', (int) $filters['school_year_id'])
            : $schoolYears->firstWhere('is_active', true) ?? $schoolYears->first();
        $students = Student::withTrashed()
            ->whereIn('id', $studentIds)
            ->with(['enrollments' => fn ($query) => $query
                ->when($schoolYear, fn ($enrollmentQuery) => $enrollmentQuery->where('school_year_id', $schoolYear->id))
                ->with('classroom')
                ->orderByDesc('enrolled_at')
                ->orderByDesc('id')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $statements = $schoolYear === null
            ? collect()
            : $feeStatements->forStudents($schoolYear, $studentIds);
        $payments = Payment::query()
            ->when(
                $schoolYear !== null && $studentIds !== [],
                fn (Builder $query) => $query->whereHas('enrollment', fn (Builder $enrollmentQuery) => $enrollmentQuery
                    ->where('school_year_id', $schoolYear->id)
                    ->whereIn('student_id', $studentIds)),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->with(['enrollment.student', 'enrollment.classroom'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('guardian.payments.index', [
            'students' => $students,
            'schoolYears' => $schoolYears,
            'schoolYear' => $schoolYear,
            'statements' => $statements,
            'payments' => $payments,
        ]);
    }
}
