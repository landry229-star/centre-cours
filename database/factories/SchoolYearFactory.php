<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2030, 2090);

        return [
            'label' => "{$year}-".($year + 1),
            'start_date' => "{$year}-10-01",
            'end_date' => ($year + 1).'-07-31',
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => true]);
    }
}
