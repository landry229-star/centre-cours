<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'classroom_id' => Classroom::factory(),
            // Toujours cohérent avec la classe (sinon la clé étrangère composite refuse la ligne).
            'school_year_id' => fn (array $attributes) => Classroom::query()->findOrFail($attributes['classroom_id'])->school_year_id,
            'enrolled_at' => now()->toDateString(),
            'ended_at' => null,
            'status' => EnrollmentStatus::Active,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Withdrawn,
            'ended_at' => now()->toDateString(),
        ]);
    }
}
