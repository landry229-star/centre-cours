<?php

namespace Database\Factories;

use App\Models\AttendanceSession;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crée aussi l'affectation (classe + matière + enseignant) exigée par la clé étrangère de la base.
 * Pour réutiliser une affectation existante : ->forAssignment($assignment).
 *
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    public function definition(): array
    {
        $assignment = TeachingAssignment::factory()->create();

        return [
            'classroom_id' => $assignment->classroom_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'session_date' => now()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => null,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function forAssignment(TeachingAssignment $assignment): static
    {
        return $this->state(fn () => [
            'classroom_id' => $assignment->classroom_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
        ]);
    }
}
