<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeCorrection>
 */
class GradeCorrectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = Grade::factory()->create();

        return [
            'grade_id' => $grade->id,
            'corrected_by' => User::factory()->admin(),
            'previous_score' => $grade->score,
            'new_score' => min(20, (float) $grade->score + 1),
            'reason' => fake()->sentence(),
        ];
    }
}
