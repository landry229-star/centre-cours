<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
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
            'room_id' => Room::factory(),
            'created_by' => User::factory()->admin(),
            'day_of_week' => DayOfWeek::Monday,
            'starts_at' => '08:00:00',
            'ends_at' => '09:00:00',
        ];
    }

    public function forAssignment(TeachingAssignment $assignment): static
    {
        return $this->state(fn () => [
            'classroom_id' => $assignment->classroom_id,
            'teaching_assignment_id' => $assignment->id,
        ]);
    }
}
