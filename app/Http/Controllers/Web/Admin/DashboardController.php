<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\ClassroomStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Enums\TeacherStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Billing\FeeStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(FeeStatementService $feeStatements): View
    {
        $schoolYear = SchoolYear::current();
        $monthStart = now()->startOfMonth()->toDateString();
        $today = today()->toDateString();
        $firstTrendMonth = CarbonImmutable::now()->subMonths(5)->startOfMonth();
        $paymentTrendRows = Payment::query()
            ->valid()
            ->whereBetween('paid_at', [$firstTrendMonth->toDateString(), $today])
            ->when($schoolYear, fn ($query) => $query->whereHas(
                'enrollment',
                fn ($enrollmentQuery) => $enrollmentQuery->where('school_year_id', $schoolYear->id),
            ))
            ->selectRaw("DATE_TRUNC('month', paid_at)::date as month, COUNT(*) as payment_count, SUM(amount) as amount")
            ->groupByRaw("DATE_TRUNC('month', paid_at)::date")
            ->get()
            ->keyBy(fn (object $row): string => CarbonImmutable::parse($row->month)->format('Y-m'));
        $enrollmentTrendRows = Enrollment::query()
            ->whereBetween('enrolled_at', [$firstTrendMonth->toDateString(), $today])
            ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->selectRaw("DATE_TRUNC('month', enrolled_at)::date as month, COUNT(*) as enrollment_count")
            ->groupByRaw("DATE_TRUNC('month', enrolled_at)::date")
            ->get()
            ->keyBy(fn (object $row): string => CarbonImmutable::parse($row->month)->format('Y-m'));
        $monthlyTrends = collect(range(0, 5))
            ->map(function (int $offset) use ($firstTrendMonth, $paymentTrendRows, $enrollmentTrendRows): array {
                $month = $firstTrendMonth->addMonths($offset);
                $key = $month->format('Y-m');
                $payment = $paymentTrendRows->get($key);
                $enrollments = $enrollmentTrendRows->get($key);

                return [
                    'key' => $key,
                    'label' => $month->locale((string) config('app.locale'))->translatedFormat('M Y'),
                    'payment_count' => (int) ($payment->payment_count ?? 0),
                    'payment_amount' => (int) ($payment->amount ?? 0),
                    'enrollment_count' => (int) ($enrollments->enrollment_count ?? 0),
                ];
            });
        $paymentMethods = Payment::query()
            ->valid()
            ->when($schoolYear, fn ($query) => $query->whereHas(
                'enrollment',
                fn ($enrollmentQuery) => $enrollmentQuery->where('school_year_id', $schoolYear->id),
            ))
            ->selectRaw('method, COUNT(*) as payment_count, SUM(amount) as amount')
            ->groupBy('method')
            ->get()
            ->keyBy('method');
        $arrears = $schoolYear === null
            ? ['amount' => 0, 'students' => 0]
            : $this->arrearsSummary($feeStatements, $schoolYear);

        return view('dashboards.admin', [
            'schoolYear' => $schoolYear,
            'metrics' => [
                'students' => Student::query()->where('status', StudentStatus::Active)->count(),
                'teachers' => Teacher::query()->where('status', TeacherStatus::Active)->count(),
                'classrooms' => Classroom::query()
                    ->where('status', ClassroomStatus::Active)
                    ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
                    ->count(),
                'enrollments' => Enrollment::query()
                    ->active()
                    ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
                    ->count(),
                'absences' => Attendance::query()
                    ->where('status', AttendanceStatus::Absent)
                    ->whereHas('session', fn ($query) => $query->whereDate('session_date', today()))
                    ->count(),
                'collected' => Payment::query()
                    ->valid()
                    ->whereBetween('paid_at', [$monthStart, $today])
                    ->when($schoolYear, fn ($query) => $query->whereHas(
                        'enrollment',
                        fn ($enrollmentQuery) => $enrollmentQuery->where('school_year_id', $schoolYear->id),
                    ))
                    ->sum('amount'),
                'payments_this_month' => Payment::query()
                    ->valid()
                    ->whereBetween('paid_at', [$monthStart, $today])
                    ->when($schoolYear, fn ($query) => $query->whereHas(
                        'enrollment',
                        fn ($enrollmentQuery) => $enrollmentQuery->where('school_year_id', $schoolYear->id),
                    ))
                    ->count(),
                'arrears_amount' => $arrears['amount'],
                'arrears_students' => $arrears['students'],
            ],
            'paymentMethods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method): array => [
                    'method' => $method,
                    'count' => (int) ($paymentMethods->get($method->value)->payment_count ?? 0),
                    'amount' => (int) ($paymentMethods->get($method->value)->amount ?? 0),
                ]),
            'monthlyTrends' => $monthlyTrends,
            'recentPayments' => Payment::query()
                ->valid()
                ->when($schoolYear, fn ($query) => $query->whereHas(
                    'enrollment',
                    fn ($enrollmentQuery) => $enrollmentQuery->where('school_year_id', $schoolYear->id),
                ))
                ->with(['enrollment.student'])
                ->latest('paid_at')
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * @return array{amount: int, students: int}
     */
    private function arrearsSummary(FeeStatementService $feeStatements, SchoolYear $schoolYear): array
    {
        $studentsWithArrears = $feeStatements->build($schoolYear)
            ->filter(fn ($statement) => $statement->arrears()->isNotEmpty());

        return [
            'amount' => (int) $studentsWithArrears->sum(
                fn ($statement) => $statement->arrears()->sum('remaining'),
            ),
            'students' => $studentsWithArrears->count(),
        ];
    }
}
