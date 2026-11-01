<?php

namespace Database\Seeders;

use App\Enums\Cycle;
use App\Models\FeeSchedule;
use App\Models\Level;
use App\Models\SchoolYear;
use Illuminate\Database\Seeder;

/**
 * Tarifs de DÉMONSTRATION pour l'année active (montants fictifs, modifiables depuis l'application).
 * Ne remplace jamais un tarif déjà défini.
 */
class FeeScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $year = SchoolYear::current();

        if ($year === null) {
            return;
        }

        $amounts = [
            Cycle::Primary->value => 8000,
            Cycle::College->value => 12000,
            Cycle::Lycee->value => 15000,
        ];

        foreach (Level::all() as $level) {
            FeeSchedule::unguarded(fn () => FeeSchedule::firstOrCreate(
                ['school_year_id' => $year->id, 'level_id' => $level->id],
                ['monthly_amount' => $amounts[$level->cycle->value]],
            ));
        }
    }
}
