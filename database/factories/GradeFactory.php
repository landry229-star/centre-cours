<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $evaluation = Evaluation::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'classroom_id' => $evaluation->classroom_id,
            'school_year_id' => $evaluation->classroom->school_year_id,
            'enrolled_at' => $evaluation->evaluation_date->toDateString(),
        ]);

        return [
            'evaluation_id' => $evaluation->id,
            'enrollment_id' => $enrollment->id,
            'classroom_id' => $evaluation->classroom_id,
            'entered_by' => User::factory()->admin(),
            'score' => fake()->randomFloat(2, 0, 20),
            'comment' => null,
        ];
    }

    public function forEvaluation(Evaluation $evaluation, Enrollment $enrollment): static
    {
        return $this->state(fn () => [
            'evaluation_id' => $evaluation->id,
            'enrollment_id' => $enrollment->id,
            'classroom_id' => $evaluation->classroom_id,
        ]);
    }
}
