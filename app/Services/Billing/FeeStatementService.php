<?php

namespace App\Services\Billing;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\FeeDueDate;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\SchoolYear;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Calcule les échéanciers. RIEN n'est stocké : le statut payé / partiel / impayé est toujours
 * recalculé depuis (tarif du niveau) et (somme des paiements valides). Une seule source de vérité.
 *
 * Règles de facturation, par élève et par année :
 *  - un mois est facturé s'il est couvert par au moins une inscription de l'élève ;
 *  - le tarif est celui du niveau de l'inscription la plus récente qui couvre ce mois
 *    (en cas de changement de classe en cours de mois, la nouvelle classe s'applique) ;
 *  - une inscription terminée par retrait ou changement de classe cesse de facturer après le mois de fin.
 */
class FeeStatementService
{
    /**
     * @return Collection<int, StudentStatement> indexée par student_id
     */
    public function build(SchoolYear $year, ?int $studentId = null, ?CarbonInterface $today = null): Collection
    {
        return $this->buildForStudents($year, $studentId === null ? null : [$studentId], $today);
    }

    /**
     * @param  list<int>  $studentIds
     * @return Collection<int, StudentStatement> indexée par student_id
     */
    public function forStudents(SchoolYear $year, array $studentIds, ?CarbonInterface $today = null): Collection
    {
        return $this->buildForStudents($year, array_values(array_unique($studentIds)), $today);
    }

    /**
     * @param  list<int>|null  $studentIds  Null builds statements for all students in the year.
     * @return Collection<int, StudentStatement> indexée par student_id
     */
    private function buildForStudents(
        SchoolYear $year,
        ?array $studentIds,
        ?CarbonInterface $today,
    ): Collection {
        $today = ($today ?? now())->copy()->startOfDay();
        $months = $this->months($year);

        $enrollments = Enrollment::query()
            ->where('school_year_id', $year->id)
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->with(['student', 'classroom.level'])
            ->orderBy('enrolled_at')
            ->orderBy('id')
            ->get();

        $fees = FeeSchedule::query()->where('school_year_id', $year->id)->pluck('monthly_amount', 'level_id');
        $dueDates = FeeDueDate::query()
            ->where('school_year_id', $year->id)
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->get(['student_id', 'month', 'due_date'])
            ->keyBy(fn (FeeDueDate $dueDate): string => $dueDate->student_id.':'.$dueDate->month->toDateString());
        $paid = $this->paidAmounts($year, $studentIds);

        $statements = collect();

        foreach ($enrollments->groupBy('student_id') as $sid => $list) {
            $rows = collect();
            $error = null;

            foreach ($months as $start) {
                $end = $start->copy()->endOfMonth();

                $covering = $list->filter(
                    fn (Enrollment $e) => $e->enrolled_at->lte($end) && $this->effectiveEnd($e, $year)->gte($start)
                );

                if ($covering->isEmpty()) {
                    continue;
                }

                $governing = $covering->last();
                $level = $governing->classroom->level;

                if (! $fees->has($level->id)) {
                    $error = "Aucun tarif défini pour le niveau « {$level->name} » ({$year->label}).";
                    $rows = collect();
                    break;
                }

                $dueDate = $dueDates->get($sid.':'.$start->toDateString())?->due_date;

                $rows->push(new MonthlyFee(
                    month: $start,
                    expected: (int) $fees[$level->id],
                    paid: (int) ($paid[$sid][$start->toDateString()] ?? 0),
                    dueDate: $dueDate,
                    due: $dueDate !== null && $dueDate->lte($today),
                    overdue: $dueDate !== null && $dueDate->lt($today),
                    enrollmentId: $governing->id,
                ));
            }

            $statements->put((int) $sid, new StudentStatement($list->first()->student, $rows, $error));
        }

        return $statements;
    }

    public function forStudent(SchoolYear $year, int $studentId, ?CarbonInterface $today = null): ?StudentStatement
    {
        return $this->build($year, $studentId, $today)->get($studentId);
    }

    /** @return list<CarbonInterface> 1er jour de chaque mois de l'année scolaire */
    private function months(SchoolYear $year): array
    {
        $months = [];
        $cursor = $year->start_date->copy()->startOfMonth();
        $last = $year->end_date->copy()->startOfMonth();

        while ($cursor->lte($last)) {
            $months[] = $cursor->copy();
            $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    /** Une inscription en cours ou terminée normalement facture jusqu'à la fin de l'année. */
    private function effectiveEnd(Enrollment $enrollment, SchoolYear $year): CarbonInterface
    {
        return match ($enrollment->status) {
            EnrollmentStatus::Active, EnrollmentStatus::Completed => $year->end_date->copy()->endOfDay(),
            default => $enrollment->ended_at->copy()->endOfDay(),
        };
    }

    /** @return array<int, array<string, int>> [student_id => ['2026-10-01' => 15000]] */
    private function paidAmounts(SchoolYear $year, ?array $studentIds): array
    {
        $totals = Payment::query()
            ->valid()
            ->join('enrollments', 'enrollments.id', '=', 'payments.enrollment_id')
            ->where('enrollments.school_year_id', $year->id)
            ->when($studentIds !== null, fn ($query) => $query->whereIn('enrollments.student_id', $studentIds))
            ->groupBy('enrollments.student_id', 'payments.month')
            ->selectRaw('enrollments.student_id as student_id, payments.month as month, SUM(payments.amount) as total')
            // toBase() : lignes brutes (stdClass). Sans cela, Eloquent appliquerait le cast « date »
            // du modèle Payment et la clé deviendrait '2030-10-01 00:00:00' au lieu de '2030-10-01'.
            ->toBase()
            ->get();

        $result = [];

        foreach ($totals as $row) {
            $result[(int) $row->student_id][(string) $row->month] = (int) $row->total;
        }

        return $result;
    }
}
