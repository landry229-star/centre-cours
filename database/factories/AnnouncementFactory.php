<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->admin(),
            'classroom_id' => null,
            'school_year_id' => null,
            'title' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'audience' => 'all',
            'published_at' => now(),
        ];
    }
}
