<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\FeeDueDate;
use App\Models\Payment;
use App\Services\Billing\FeeStatementService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Échéancier d'un élève pour l'année de l'inscription : Mois | Attendu | Payé | Reste | Statut. */
class EnrollmentStatementController extends Controller
{
    public function __invoke(Enrollment $enrollment, FeeStatementService $statements): View
    {
        Gate::authorize('viewAny', Payment::class);

        $enrollment->load(['student', 'classroom.level', 'schoolYear']);
        $dueDates = FeeDueDate::query()
            ->where('student_id', $enrollment->student_id)
            ->where('school_year_id', $enrollment->school_year_id)
            ->with('changes.changedBy')
            ->get()
            ->keyBy(fn (FeeDueDate $dueDate): string => $dueDate->month->toDateString());

        return view('admin.payments.statement', [
            'enrollment' => $enrollment,
            'statement' => $statements->forStudent($enrollment->schoolYear, $enrollment->student_id),
            'dueDates' => $dueDates,
            'payments' => Payment::query()
                ->whereHas('enrollment', fn ($e) => $e
                    ->where('student_id', $enrollment->student_id)
                    ->where('school_year_id', $enrollment->school_year_id))
                ->orderByDesc('paid_at')->orderByDesc('id')
                ->get(),
        ]);
    }
}
