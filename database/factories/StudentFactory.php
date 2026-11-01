<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StudentStatus;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'guardian_id' => Guardian::factory(),
            'matricule' => 'ELV-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => fake()->firstName(),
            'last_name' => mb_strtoupper(fake()->lastName()),
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_date' => fake()->dateTimeBetween('-18 years', '-6 years')->format('Y-m-d'),
            'birth_place' => fake()->city(),
            'phone' => null,
            'address' => fake()->streetAddress(),
            'guardian_relationship' => fake()->randomElement(GuardianRelationship::cases()),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '+229 01 '.fake()->numerify('## ## ## ##'),
            'status' => StudentStatus::Active,
            'qr_token' => Str::random(40),
        ];
    }
}
