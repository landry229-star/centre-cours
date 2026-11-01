<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Ligne de présence « brute » (sans le service). La séance et l'inscription sont créées dans la MÊME classe.
 *
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        $session = AttendanceSession::factory()->create();
        $enrollment = Enrollment::factory()->create(['classroom_id' => $session->classroom_id]);

        return [
            'attendance_session_id' => $session->id,
            'classroom_id' => $session->classroom_id,
            'enrollment_id' => $enrollment->id,
            'status' => 'present',
            'arrived_at' => null,
            'note' => null,
            'marked_by' => User::factory()->admin(),
        ];
    }
}
