<?php

namespace Database\Factories;

use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => mb_strtoupper(fake()->lastName()),
            'phone' => '+229 01 '.fake()->numerify('## ## ## ##'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'profession' => fake()->jobTitle(),
        ];
    }
}
