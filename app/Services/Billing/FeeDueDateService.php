<?php

namespace App\Services\Billing;

use App\Models\Enrollment;
use App\Models\FeeDueDate;
use App\Models\FeeDueDateChange;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class FeeDueDateService
{
    public function __construct(private readonly FeeStatementService $statements) {}

    public function update(
        Enrollment $enrollment,
        CarbonInterface $month,
        ?CarbonInterface $dueDate,
        User $changedBy,
    ): bool {
        $month = $month->copy()->startOfMonth();
        $dueDate = $dueDate?->copy()->startOfDay();
        $enrollment->loadMissing('schoolYear');

        return DB::transaction(function () use ($enrollment, $month, $dueDate, $changedBy): bool {
            DB::table('students')->where('id', $enrollment->student_id)->lockForUpdate()->first();
            DB::table('school_years')->where('id', $enrollment->school_year_id)->lockForUpdate()->first();

            $statement = $this->statements->forStudent($enrollment->schoolYear, $enrollment->student_id);

            if ($statement === null || $statement->error !== null) {
                throw new DomainException($statement?->error ?? 'Aucun échéancier pour cet élève.');
            }

            $row = $statement->rows->first(
                fn (MonthlyFee $fee): bool => $fee->month->isSameDay($month),
            );

            if ($row === null) {
                throw new DomainException('Ce mois ne fait pas partie de l’échéancier de cet élève.');
            }

            $schedule = FeeDueDate::query()
                ->where('student_id', $enrollment->student_id)
                ->where('school_year_id', $enrollment->school_year_id)
                ->whereDate('month', $month->toDateString())
                ->lockForUpdate()
                ->first();

            $oldDate = $schedule?->due_date;

            if ($oldDate?->toDateString() === $dueDate?->toDateString()) {
                return false;
            }

            if ($schedule === null && $dueDate === null) {
                return false;
            }

            if ($schedule === null) {
                $schedule = new FeeDueDate;
                $schedule->forceFill([
                    'student_id' => $enrollment->student_id,
                    'school_year_id' => $enrollment->school_year_id,
                    'month' => $month->toDateString(),
                    'due_date' => $dueDate?->toDateString(),
                ])->save();
            } else {
                $schedule->forceFill(['due_date' => $dueDate?->toDateString()])->save();
            }

            $change = new FeeDueDateChange;
            $change->forceFill([
                'fee_due_date_id' => $schedule->id,
                'old_due_date' => $oldDate?->toDateString(),
                'new_due_date' => $dueDate?->toDateString(),
                'changed_by' => $changedBy->id,
            ])->save();

            return true;
        });
    }
}
