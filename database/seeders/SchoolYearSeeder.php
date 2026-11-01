<?php

namespace Database\Seeders;

use App\Models\SchoolYear;
use Illuminate\Database\Seeder;

class SchoolYearSeeder extends Seeder
{
    public function run(): void
    {
        $year = SchoolYear::firstOrCreate(
            ['label' => '2026-2027'],
            ['start_date' => '2026-10-01', 'end_date' => '2027-07-31'],
        );

        // is_active n'est pas fillable : activation explicite.
        if (! SchoolYear::active()->exists()) {
            $year->forceFill(['is_active' => true])->save();
        }
    }
}
