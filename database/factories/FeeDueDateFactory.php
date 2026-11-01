<?php

namespace Database\Factories;

use App\Models\FeeDueDate;
use App\Models\SchoolYear;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeDueDate>
 */
class FeeDueDateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'school_year_id' => SchoolYear::factory(),
            'month' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->addDays(9)->toDateString(),
        ];
    }
}
