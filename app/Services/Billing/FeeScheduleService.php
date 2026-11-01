<?php

namespace App\Services\Billing;

use App\Models\FeeSchedule;
use App\Models\Level;
use App\Models\Payment;
use App\Models\SchoolYear;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class FeeScheduleService
{
    /**
     * Définit (ou modifie) le tarif mensuel d'un niveau pour une année.
     * Règle : un tarif ne change plus dès qu'un paiement existe pour ce niveau et cette année,
     * sinon les mensualités déjà encaissées changeraient de statut après coup.
     */
    public function set(SchoolYear $year, Level $level, int $amount): FeeSchedule
    {
        try {
            return DB::transaction(function () use ($year, $level, $amount) {
                SchoolYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();

                $fee = FeeSchedule::query()
                    ->where('school_year_id', $year->id)
                    ->where('level_id', $level->id)
                    ->lockForUpdate()
                    ->first();

                if ($fee === null) {
                    $fee = new FeeSchedule;
                    $fee->forceFill([
                        'school_year_id' => $year->id,
                        'level_id' => $level->id,
                        'monthly_amount' => $amount,
                    ])->save();

                    return $fee;
                }

                if ($fee->monthly_amount === $amount) {
                    return $fee;
                }

                $this->assertNoPayments($fee);
                $fee->forceFill(['monthly_amount' => $amount])->save();

                return $fee;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Ce tarif vient d\'être défini par quelqu\'un d\'autre. Rechargez la page.');
        }
    }

    public function delete(FeeSchedule $fee): void
    {
        DB::transaction(function () use ($fee) {
            SchoolYear::query()->whereKey($fee->school_year_id)->lockForUpdate()->firstOrFail();
            $this->assertNoPayments($fee);
            $fee->delete();
        });
    }

    public function hasPayments(FeeSchedule $fee): bool
    {
        return Payment::query()
            ->whereHas('enrollment', fn ($e) => $e
                ->where('school_year_id', $fee->school_year_id)
                ->whereHas('classroom', fn ($c) => $c->where('level_id', $fee->level_id)))
            ->exists();
    }

    private function assertNoPayments(FeeSchedule $fee): void
    {
        if ($this->hasPayments($fee)) {
            throw new DomainException('Des paiements existent déjà pour ce niveau et cette année : le tarif est verrouillé.');
        }
    }
}
