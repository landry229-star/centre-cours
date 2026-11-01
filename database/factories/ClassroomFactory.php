<?php

namespace Database\Factories;

use App\Enums\ClassroomStatus;
use App\Models\Classroom;
use App\Models\Level;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_year_id' => SchoolYear::factory(),
            'level_id' => Level::factory(),
            'name' => 'Classe '.fake()->unique()->numberBetween(100, 99999),
            'capacity' => 40,
            'room_id' => null,
            'head_teacher_id' => null,
            'status' => ClassroomStatus::Active,
        ];
    }
}
