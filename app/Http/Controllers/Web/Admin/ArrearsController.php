<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Services\Billing\FeeStatementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * Impayés exigibles : mensualités dont le mois a commencé et qui ne sont pas soldées.
 * Calculé en mémoire par année scolaire : très largement suffisant pour un centre (quelques centaines
 * d'élèves). Pour des milliers d'élèves, on déplacerait le calcul en SQL (voir Phase 17).
 */
class ArrearsController extends Controller
{
    public function __invoke(Request $request, FeeStatementService $statements): View
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $year = isset($filters['school_year_id'])
            ? SchoolYear::find($filters['school_year_id'])
            : SchoolYear::current();

        $rows = collect();
        $withoutFee = collect();

        if ($year !== null) {
            $search = mb_strtolower(trim($filters['search'] ?? ''));

            foreach ($statements->build($year) as $statement) {
                if ($search !== '' && ! str_contains(mb_strtolower($statement->student->full_name.' '.$statement->student->matricule), $search)) {
                    continue;
                }

                if ($statement->error !== null) {
                    $withoutFee->push($statement);

                    continue;
                }

                foreach ($statement->arrears() as $fee) {
                    $rows->push(['student' => $statement->student, 'fee' => $fee]);
                }
            }
        }

        $rows = $rows->sortBy([
            fn ($a, $b) => strcmp($a['student']->full_name, $b['student']->full_name),
            fn ($a, $b) => $a['fee']->month <=> $b['fee']->month,
        ])->values();

        $perPage = 25;
        $page = max(1, (int) $request->input('page', 1));
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.payments.arrears', [
            'year' => $year,
            'rows' => $paginator,
            'totalRemaining' => (int) $rows->sum(fn ($r) => $r['fee']->remaining),
            'studentsCount' => $rows->pluck('student.id')->unique()->count(),
            'withoutFee' => $withoutFee,
            'schoolYears' => SchoolYear::orderByDesc('start_date')->pluck('label', 'id')->all(),
        ]);
    }
}
