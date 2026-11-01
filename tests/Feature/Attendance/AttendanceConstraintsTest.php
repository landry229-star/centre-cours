<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PostgreSQL est le dernier rempart : ces tests contournent volontairement le service.
 * Une seule instruction qui échoue par test (une transaction PostgreSQL avortée est inutilisable).
 */
class AttendanceConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_attendance_row_is_unique_per_session_and_enrollment(): void
    {
        $attendance = Attendance::factory()->create();

        $this->expectException(QueryException::class);
        Attendance::factory()->create([
            'attendance_session_id' => $attendance->attendance_session_id,
            'classroom_id' => $attendance->classroom_id,
            'enrollment_id' => $attendance->enrollment_id,
        ]);
    }

    public function test_a_session_slot_is_unique(): void
    {
        $session = AttendanceSession::factory()->create();

        $this->expectException(QueryException::class);
        AttendanceSession::factory()->create([
            'classroom_id' => $session->classroom_id,
            'subject_id' => $session->subject_id,
            'teacher_id' => $session->teacher_id,
            'session_date' => $session->session_date->toDateString(),
            'starts_at' => '08:00:00',
        ]);
    }

    public function test_a_daily_call_is_unique_per_class_and_date(): void
    {
        $session = AttendanceSession::factory()->create();
        DB::table('attendance_sessions')->where('id', $session->id)->update([
            'session_type' => 'daily',
            'subject_id' => null,
            'starts_at' => '00:00:00',
        ]);

        $this->expectException(QueryException::class);
        AttendanceSession::factory()->create([
            'session_type' => 'daily',
            'classroom_id' => $session->classroom_id,
            'subject_id' => null,
            'teacher_id' => $session->teacher_id,
            'session_date' => $session->session_date->toDateString(),
            'starts_at' => '00:00:00',
        ]);
    }

    public function test_a_session_needs_a_subject_really_taught_in_that_class(): void
    {
        $assignment = TeachingAssignment::factory()->create();

        $this->expectException(QueryException::class);
        AttendanceSession::factory()->create([
            'classroom_id' => $assignment->classroom_id,
            'subject_id' => Subject::factory()->create()->id, // matière non enseignée dans cette classe
            'teacher_id' => $assignment->teacher_id,
        ]);
    }

    public function test_an_enrollment_from_another_class_cannot_be_marked(): void
    {
        $session = AttendanceSession::factory()->create();
        $foreign = Enrollment::factory()->create(); // inscrit dans une AUTRE classe

        $this->expectException(QueryException::class);
        Attendance::factory()->create([
            'attendance_session_id' => $session->id,
            'classroom_id' => $session->classroom_id,
            'enrollment_id' => $foreign->id,
        ]);
    }

    public function test_the_attendance_classroom_must_match_the_session(): void
    {
        $session = AttendanceSession::factory()->create();
        $foreign = Enrollment::factory()->create();

        $this->expectException(QueryException::class);
        // Séance de la classe A, mais ligne déclarée dans la classe B (avec un élève de B) : refusé par la clé composite.
        Attendance::factory()->create([
            'attendance_session_id' => $session->id,
            'classroom_id' => $foreign->classroom_id,
            'enrollment_id' => $foreign->id,
        ]);
    }

    public function test_an_arrival_time_requires_present_or_late(): void
    {
        $this->expectException(QueryException::class);
        Attendance::factory()->create(['status' => 'absent', 'arrived_at' => '08:10:00']);
    }

    public function test_status_must_be_known(): void
    {
        $attendance = Attendance::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('attendances')->where('id', $attendance->id)->update(['status' => 'sleeping']);
    }

    public function test_a_session_must_end_after_it_starts(): void
    {
        $this->expectException(QueryException::class);
        AttendanceSession::factory()->create(['starts_at' => '09:00:00', 'ends_at' => '08:00:00']);
    }

    public function test_an_assignment_with_sessions_cannot_be_deleted_in_the_database(): void
    {
        $session = AttendanceSession::factory()->create();
        $assignment = TeachingAssignment::where('classroom_id', $session->classroom_id)
            ->where('subject_id', $session->subject_id)->firstOrFail();

        $this->expectException(QueryException::class);
        $assignment->delete();
    }
}
