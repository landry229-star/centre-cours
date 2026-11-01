<?php

namespace Database\Factories;

use App\Models\FeeSchedule;
use App\Models\Level;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeSchedule>
 */
class FeeScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_year_id' => SchoolYear::factory(),
            'level_id' => Level::factory(),
            'monthly_amount' => 15000,
        ];
    }
}
