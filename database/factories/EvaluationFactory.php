<?php

namespace Database\Factories;

use App\Enums\EvaluationType;
use App\Models\Evaluation;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $assignment = TeachingAssignment::factory()->create();

        return [
            'classroom_id' => $assignment->classroom_id,
            'teaching_assignment_id' => $assignment->id,
            'teacher_id' => $assignment->teacher_id,
            'created_by' => User::factory()->admin(),
            'title' => fake()->words(3, true),
            'type' => fake()->randomElement(EvaluationType::cases()),
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
            'subject_coefficient' => $assignment->coefficient,
        ];
    }

    public function forAssignment(TeachingAssignment $assignment): static
    {
        return $this->state(fn () => [
            'classroom_id' => $assignment->classroom_id,
            'teaching_assignment_id' => $assignment->id,
            'teacher_id' => $assignment->teacher_id,
            'subject_coefficient' => $assignment->coefficient,
        ]);
    }
}
