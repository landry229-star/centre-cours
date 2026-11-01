<?php

namespace App\Services\Billing;

use App\Models\Student;
use Illuminate\Support\Collection;

/** Échéancier d'un élève pour une année scolaire. */
final class StudentStatement
{
    /** @param  Collection<int, MonthlyFee>  $rows */
    public function __construct(
        public readonly Student $student,
        public readonly Collection $rows,
        public readonly ?string $error = null,   // ex. « Aucun tarif défini pour le niveau 6ème »
    ) {}

    public function totalExpected(): int
    {
        return (int) $this->rows->sum('expected');
    }

    public function totalPaid(): int
    {
        return (int) $this->rows->sum('paid');
    }

    public function totalRemaining(): int
    {
        return (int) $this->rows->sum('remaining');
    }

    /** @return Collection<int, MonthlyFee> */
    public function arrears(): Collection
    {
        return $this->rows->filter(fn (MonthlyFee $row) => $row->isArrear())->values();
    }
}
