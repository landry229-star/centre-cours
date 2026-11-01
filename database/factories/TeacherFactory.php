<?php

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\TeacherStatus;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'matricule' => 'ENS-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => fake()->firstName(),
            'last_name' => mb_strtoupper(fake()->lastName()),
            'gender' => fake()->randomElement(Gender::cases()),
            'phone' => '+229 01 '.fake()->numerify('## ## ## ##'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'specialty' => fake()->randomElement(['Mathématiques', 'Français', 'Anglais', 'SVT', 'Physique-Chimie']),
            'education_level' => EducationLevel::Licence,
            'hired_at' => fake()->dateTimeBetween('-8 years', '-1 month')->format('Y-m-d'),
            'status' => TeacherStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TeacherStatus::Inactive]);
    }
}
