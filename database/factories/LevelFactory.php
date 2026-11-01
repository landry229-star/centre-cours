<?php

namespace Database\Factories;

use App\Enums\Cycle;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Level>
 */
class LevelFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(100, 9999);

        return [
            'name' => "Niveau {$n}",
            'cycle' => Cycle::College,
            'position' => $n,
        ];
    }
}
