<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use App\Models\SchoolYearPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolYearPeriod>
 */
class SchoolYearPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schoolYear = SchoolYear::factory()->create([
            'start_date' => '2030-08-01',
            'end_date' => '2031-05-31',
        ]);

        return [
            'school_year_id' => $schoolYear->id,
            'period_type' => 'annual',
            'period_number' => 1,
            'start_date' => $schoolYear->start_date,
            'end_date' => $schoolYear->end_date,
        ];
    }
}
