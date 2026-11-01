<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Matière '.fake()->unique()->numberBetween(100, 99999),
            'code' => strtoupper(fake()->unique()->lexify('????')),
        ];
    }
}
