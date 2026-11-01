<?php

namespace App\Services\Reporting;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolYear;
use App\Services\Billing\FeeStatementService;
use App\Services\Grades\GradebookService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SchoolYearReportService
{
    public function __construct(
        private FeeStatementService $feeStatements,
        private GradebookService $gradebook,
    ) {}

    /**
     * @return array{
     *     classes: Collection<int, array{
     *         name: string,
     *         status: string,
     *         enrollments: int,
     *         attendance: array<string, int>,
     *         attendance_total: int,
     *         grades_average: float|null,
     *         grades_count: int,
     *         grades_students: int,
     *         expected_due: int,
     *         paid_due: int,
     *         remaining_due: int,
     *         arrears_students: int
     *     }>,
     *     totals: array{
     *         enrollments: int,
     *         attendance: array<string, int>,
     *         attendance_total: int,
     *         grades_count: int,
     *         grades_students: int,
     *         grades_average: float|null,
     *         expected_due: int,
     *         paid_due: int,
     *         remaining_due: int,
     *         classes_with_arrears: int,
     *         students_without_fee_schedule: int
     *     }
     * }
     */
    public function generate(
        SchoolYear $schoolYear,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?int $classroomId = null,
    ): array {
        $classrooms = Classroom::withTrashed()
            ->where('school_year_id', $schoolYear->id)
            ->when($classroomId, fn ($query, int $id) => $query->whereKey($id))
            ->orderBy('name')
            ->get();

        $rows = $classrooms->mapWithKeys(fn (Classroom $classroom): array => [
            $classroom->id => [
                'name' => $classroom->name,
                'status' => $classroom->trashed() ? 'Archivée' : $classroom->status->label(),
                'enrollments' => 0,
                'attendance' => array_fill_keys(array_map(
                    fn (AttendanceStatus $status): string => $status->value,
                    AttendanceStatus::cases(),
                ), 0),
                'attendance_total' => 0,
                'grades_average' => null,
                'grades_count' => 0,
                'grades_students' => 0,
                'expected_due' => 0,
                'paid_due' => 0,
                'remaining_due' => 0,
                'arrears_students' => 0,
            ],
        ]);

        if ($classrooms->isEmpty()) {
            return ['classes' => $rows, 'totals' => $this->totals($rows)];
        }

        $classroomIds = $classrooms->modelKeys();

        $enrollmentCounts = Enrollment::query()
            ->where('school_year_id', $schoolYear->id)
            ->whereIn('classroom_id', $classroomIds)
            ->whereDate('enrolled_at', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodEnd): void {
                $query->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
                    ->orWhereDate('ended_at', '>=', $periodEnd->toDateString());
            })
            ->selectRaw('classroom_id, COUNT(DISTINCT student_id) as total')
            ->groupBy('classroom_id')
            ->toBase()
            ->pluck('total', 'classroom_id');

        foreach ($enrollmentCounts as $classroomKey => $count) {
            $row = $rows->get((int) $classroomKey);
            $row['enrollments'] = (int) $count;
            $rows->put((int) $classroomKey, $row);
        }

        $attendanceCounts = Attendance::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendances.attendance_session_id')
            ->whereIn('attendance_sessions.classroom_id', $classroomIds)
            ->whereBetween('attendance_sessions.session_date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->selectRaw('attendance_sessions.classroom_id, attendances.status, COUNT(*) as total')
            ->groupBy('attendance_sessions.classroom_id', 'attendances.status')
            ->toBase()
            ->get();

        foreach ($attendanceCounts as $count) {
            $key = (int) $count->classroom_id;
            $row = $rows->get($key);
            $row['attendance'][$count->status] = (int) $count->total;
            $row['attendance_total'] += (int) $count->total;
            $rows->put($key, $row);
        }

        $gradesByClassroom = Grade::query()
            ->select(['id', 'enrollment_id', 'classroom_id', 'evaluation_id', 'score'])
            ->whereIn('classroom_id', $classroomIds)
            ->whereNotNull('score')
            ->whereHas('evaluation', fn ($query) => $query
                ->whereNotNull('published_at')
                ->whereBetween('evaluation_date', [
                    $periodStart->toDateString(),
                    $periodEnd->toDateString(),
                ]))
            ->with(['enrollment:id,student_id', 'evaluation.assignment.subject'])
            ->get()
            ->groupBy('classroom_id');

        foreach ($gradesByClassroom as $classroomKey => $classGrades) {
            $key = (int) $classroomKey;
            $studentAverages = $classGrades
                ->groupBy(fn (Grade $grade): int => (int) $grade->enrollment->student_id)
                ->map(fn (Collection $studentGrades): ?float => $this->gradebook->averagesForGrades($studentGrades)['average'])
                ->filter(fn (?float $average): bool => $average !== null);
            $row = $rows->get($key);
            $row['grades_average'] = (float) $studentAverages->avg();
            $row['grades_count'] = $classGrades->count();
            $row['grades_students'] = $studentAverages->count();
            $rows->put($key, $row);
        }

        $enrollmentClassrooms = Enrollment::query()
            ->where('school_year_id', $schoolYear->id)
            ->whereIn('classroom_id', $classroomIds)
            ->get(['id', 'student_id', 'classroom_id'])
            ->keyBy('id');
        $feeStatements = $this->feeStatements->build($schoolYear);
        $feePeriodStart = $periodStart->toDateString() === $schoolYear->start_date->toDateString()
            ? $periodStart->startOfMonth()->toDateString()
            : $periodStart->toDateString();
        $missingFeeStudentIds = $feeStatements
            ->filter(fn ($statement): bool => $statement->error !== null)
            ->keys()
            ->map(fn ($studentId): int => (int) $studentId)
            ->all();
        $arrearsStudentsByClass = [];

        foreach ($feeStatements as $studentId => $statement) {
            if ($statement->error !== null) {
                continue;
            }

            foreach ($statement->rows as $monthlyFee) {
                if ($monthlyFee->dueDate === null
                    || $monthlyFee->dueDate->toDateString() > $periodEnd->toDateString()
                    || $monthlyFee->month->toDateString() < $feePeriodStart
                    || $monthlyFee->month->toDateString() > $periodEnd->toDateString()) {
                    continue;
                }

                $enrollment = $enrollmentClassrooms->get($monthlyFee->enrollmentId);

                if ($enrollment === null) {
                    continue;
                }

                $key = (int) $enrollment->classroom_id;
                $row = $rows->get($key);
                $row['expected_due'] += $monthlyFee->expected;
                $row['paid_due'] += min($monthlyFee->paid, $monthlyFee->expected);
                $row['remaining_due'] += $monthlyFee->remaining;
                $rows->put($key, $row);

                if (
                    $monthlyFee->remaining > 0
                    && $monthlyFee->dueDate->toDateString() < $periodEnd->toDateString()
                ) {
                    $arrearsStudentsByClass[$key][(int) $studentId] = true;
                }
            }
        }

        foreach ($arrearsStudentsByClass as $classroomKey => $studentIds) {
            $row = $rows->get((int) $classroomKey);
            $row['arrears_students'] = count($studentIds);
            $rows->put((int) $classroomKey, $row);
        }

        $totals = $this->totals($rows);
        $totals['students_without_fee_schedule'] = count($missingFeeStudentIds);

        return ['classes' => $rows, 'totals' => $totals];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(Collection $rows): array
    {
        $attendance = [];

        foreach (AttendanceStatus::cases() as $status) {
            $attendance[$status->value] = (int) $rows->sum(
                fn (array $row): int => $row['attendance'][$status->value],
            );
        }

        $gradesCount = (int) $rows->sum('grades_count');
        $gradeStudentsCount = (int) $rows->sum('grades_students');
        $weightedGradeTotal = $rows->sum(
            fn (array $row): float => ($row['grades_average'] ?? 0) * $row['grades_students'],
        );

        return [
            'enrollments' => (int) $rows->sum('enrollments'),
            'attendance' => $attendance,
            'attendance_total' => array_sum($attendance),
            'grades_count' => $gradesCount,
            'grades_students' => $gradeStudentsCount,
            'grades_average' => $gradeStudentsCount > 0 ? $weightedGradeTotal / $gradeStudentsCount : null,
            'expected_due' => (int) $rows->sum('expected_due'),
            'paid_due' => (int) $rows->sum('paid_due'),
            'remaining_due' => (int) $rows->sum('remaining_due'),
            'classes_with_arrears' => $rows->where('remaining_due', '>', 0)->count(),
            'students_without_fee_schedule' => 0,
        ];
    }
}
