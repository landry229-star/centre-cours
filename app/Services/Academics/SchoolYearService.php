<?php

namespace App\Services\Academics;

use App\Models\Payment;
use App\Models\SchoolYear;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SchoolYearService
{
    /** @param array<string, mixed> $data label, start_date, end_date */
    public function create(array $data): SchoolYear
    {
        return SchoolYear::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(SchoolYear $year, array $data): SchoolYear
    {
        return DB::transaction(function () use ($year, $data) {
            $year = SchoolYear::query()->whereKey($year->id)->lockForUpdate()->firstOrFail();
            $startDate = Carbon::parse($data['start_date'])->toDateString();
            $endDate = Carbon::parse($data['end_date'])->toDateString();
            $periodChanged = $startDate !== $year->start_date->toDateString()
                || $endDate !== $year->end_date->toDateString();

            if ($periodChanged) {
                $hasHistory = Payment::query()
                    ->whereHas('enrollment', fn ($query) => $query->where('school_year_id', $year->id))
                    ->exists()
                    || $year->enrollments()->exists()
                    || $year->periods()->exists();

                if ($hasHistory) {
                    throw new DomainException('Impossible de modifier les dates de cette année scolaire : des inscriptions, des paiements ou des périodes sont déjà enregistrés pour cette année.');
                }
            }

            $year->update($data);

            return $year;
        });
    }

    /**
     * Active une année scolaire de façon atomique :
     *  1. verrouille les années (deux administrateurs simultanés ne peuvent pas se marcher dessus) ;
     *  2. termine les inscriptions en cours de l'ancienne année active ;
     *  3. désactive l'ancienne année PUIS active la nouvelle (l'index unique partiel
     *     n'accepte jamais deux années actives, même un instant).
     */
    public function activate(SchoolYear $year): SchoolYear
    {
        return DB::transaction(function () use ($year) {
            SchoolYear::query()->lockForUpdate()->get();

            $previous = SchoolYear::active()->first();

            if ($previous !== null && $previous->is($year)) {
                return $year;
            }

            if ($previous !== null) {
                $this->completeEnrollments($previous);
                SchoolYear::where('is_active', true)->update(['is_active' => false]);
            }

            $year->forceFill(['is_active' => true])->save();

            return $year;
        });
    }

    public function delete(SchoolYear $year): void
    {
        if ($year->is_active) {
            throw new DomainException("Impossible de supprimer l'année active. Activez d'abord une autre année.");
        }

        if ($year->classrooms()->withTrashed()->exists()) {
            throw new DomainException('Impossible de supprimer cette année : des classes y sont rattachées.');
        }

        $year->delete();
    }

    /**
     * Les inscriptions encore « en cours » deviennent « terminées ».
     * ended_at = la plus grande des deux dates (date d'inscription, fin de l'année) pour respecter
     * la contrainte ended_at >= enrolled_at même si l'élève s'est inscrit après la fin officielle.
     */
    private function completeEnrollments(SchoolYear $year): void
    {
        DB::update(
            "UPDATE enrollments
                SET status = 'completed', ended_at = GREATEST(enrolled_at, ?::date), updated_at = now()
              WHERE school_year_id = ? AND status = 'active'",
            [$year->end_date->toDateString(), $year->id]
        );
    }
}
