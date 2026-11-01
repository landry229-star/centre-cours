<?php

namespace Tests\Feature\Attendance;

use App\Http\Controllers\Web\Guardian\AttendanceHistoryController;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesAttendance;
use Tests\TestCase;

/**
 * Règle d'or : un parent ne voit JAMAIS les absences des enfants d'un autre parent.
 */
class AttendancePolicyTest extends TestCase
{
    use CreatesAttendance, RefreshDatabase;

    public function test_guardian_sees_only_their_own_childs_attendance(): void
    {
        $owner = User::factory()->guardian()->create();
        $stranger = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $owner->id]);
        Guardian::factory()->create(['user_id' => $stranger->id]);

        $setup = $this->attendanceSetup(0);
        $enrollment = Enrollment::factory()->create([
            'student_id' => Student::factory()->for($guardian)->create()->id,
            'classroom_id' => $setup['classroom']->id,
        ]);
        $session = AttendanceSession::factory()->forAssignment($setup['assignment'])->create();
        $attendance = Attendance::factory()->create([
            'attendance_session_id' => $session->id,
            'classroom_id' => $session->classroom_id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->assertTrue($owner->can('view', $attendance));
        $this->assertFalse($stranger->can('view', $attendance));
        $this->assertFalse(User::factory()->student()->create()->can('view', $attendance));
    }

    public function test_guardian_history_shows_only_their_children(): void
    {
        $owner = User::factory()->guardian()->create();
        $stranger = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $owner->id]);
        $strangerProfile = Guardian::factory()->create(['user_id' => $stranger->id]);
        $setup = $this->attendanceSetup(0);
        $mine = Student::factory()->for($guardian)->create(['first_name' => 'EnfantVisible']);
        $theirs = Student::factory()->for($strangerProfile)->create(['first_name' => 'EnfantPrive']);
        $session = AttendanceSession::factory()->forAssignment($setup['assignment'])->create();

        foreach ([$mine, $theirs] as $student) {
            $enrollment = Enrollment::factory()->create([
                'student_id' => $student->id,
                'classroom_id' => $setup['classroom']->id,
            ]);
            Attendance::factory()->create([
                'attendance_session_id' => $session->id,
                'classroom_id' => $session->classroom_id,
                'enrollment_id' => $enrollment->id,
            ]);
        }

        $this->actingAs($owner)
            ->get(route('guardian.attendance.index'))
            ->assertOk()
            ->assertSee('EnfantVisible')
            ->assertDontSee('EnfantPrive');

        $this->actingAs($stranger)
            ->get(route('guardian.attendance.index'))
            ->assertOk()
            ->assertSee('EnfantPrive')
            ->assertDontSee('EnfantVisible');

        $this->actingAs($owner)
            ->get(route('guardian.attendance.index', ['student_id' => $theirs->id]))
            ->assertOk()
            ->assertSee('Aucune présence enregistrée')
            ->assertDontSee('EnfantPrive');
    }

    public function test_guardian_filters_and_csv_remain_scoped_to_their_own_children(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $owner = User::factory()->guardian()->create();
        $otherParent = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $owner->id]);
        $otherGuardian = Guardian::factory()->create(['user_id' => $otherParent->id]);
        $setup = $this->attendanceSetup(0);
        $mine = Student::factory()->for($guardian)->create(['first_name' => 'EnfantExportable']);
        $private = Student::factory()->for($otherGuardian)->create(['first_name' => 'EnfantConfidentiel']);
        $recent = AttendanceSession::factory()->forAssignment($setup['assignment'])->create([
            'session_date' => now()->subDay()->toDateString(),
        ]);
        $old = AttendanceSession::factory()->forAssignment($setup['assignment'])->create([
            'session_date' => now()->subDays(10)->toDateString(),
        ]);
        $mineEnrollment = Enrollment::factory()->create([
            'student_id' => $mine->id,
            'classroom_id' => $setup['classroom']->id,
        ]);
        $privateEnrollment = Enrollment::factory()->create([
            'student_id' => $private->id,
            'classroom_id' => $setup['classroom']->id,
        ]);

        foreach ([[$mineEnrollment, $recent, 'excused'], [$mineEnrollment, $old, 'absent'], [$privateEnrollment, $recent, 'excused']] as [$enrollment, $session, $status]) {
            Attendance::factory()->create([
                'attendance_session_id' => $session->id,
                'classroom_id' => $session->classroom_id,
                'enrollment_id' => $enrollment->id,
                'status' => $status,
            ]);
        }

        $filters = [
            'student_id' => $mine->id,
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
            'status' => 'excused',
        ];
        $this->actingAs($owner)
            ->get(route('guardian.attendance.index', $filters))
            ->assertOk()
            ->assertSee('EnfantExportable')
            ->assertDontSee('EnfantConfidentiel')
            ->assertSee('Absence justifiée : 1')
            ->assertSee('Absent : 0');

        $request = Request::create('/parent/presences/export', 'GET', $filters);
        $request->setUserResolver(fn () => $owner);
        $this->actingAs($owner);
        $response = app(AttendanceHistoryController::class)->export($request);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        expect($csv)
            ->toContain('EnfantExportable')
            ->toContain($recent->session_date->toDateString())
            ->not->toContain('EnfantConfidentiel')
            ->toContain('Absence justifiée')
            ->not->toContain('EnfantAbsent');
    }

    public function test_guardian_without_a_linked_profile_cannot_open_attendance_history(): void
    {
        $this->actingAs(User::factory()->guardian()->create())
            ->get(route('guardian.attendance.index'))
            ->assertForbidden();
    }

    public function test_student_sees_only_their_own_attendance(): void
    {
        $account = User::factory()->student()->create();
        $setup = $this->attendanceSetup(0);
        $enrollment = Enrollment::factory()->create([
            'student_id' => Student::factory()->create(['user_id' => $account->id])->id,
            'classroom_id' => $setup['classroom']->id,
        ]);
        $session = AttendanceSession::factory()->forAssignment($setup['assignment'])->create();
        $mine = Attendance::factory()->create([
            'attendance_session_id' => $session->id, 'classroom_id' => $session->classroom_id, 'enrollment_id' => $enrollment->id,
        ]);
        $other = Attendance::factory()->create();

        $this->assertTrue($account->can('view', $mine));
        $this->assertFalse($account->can('view', $other));
    }

    public function test_only_the_teacher_of_the_session_and_the_admin_can_view_attendance_rows(): void
    {
        $setup = $this->attendanceSetup(0);
        $other = $this->attendanceSetup(0);
        $session = AttendanceSession::factory()->forAssignment($setup['assignment'])->create();
        $enrollment = Enrollment::factory()->create(['classroom_id' => $setup['classroom']->id]);
        $attendance = Attendance::factory()->create([
            'attendance_session_id' => $session->id, 'classroom_id' => $session->classroom_id, 'enrollment_id' => $enrollment->id,
        ]);

        $this->assertTrue($setup['user']->can('view', $attendance));
        $this->assertFalse($other['user']->can('view', $attendance));
        $this->assertTrue(User::factory()->admin()->create()->can('view', $attendance));
    }

    public function test_nobody_can_delete_a_session(): void
    {
        $session = AttendanceSession::factory()->create();

        $this->assertFalse(User::factory()->admin()->create()->can('delete', $session));
    }

    public function test_removing_an_assignment_with_sessions_is_blocked_with_a_clear_message(): void
    {
        $setup = $this->attendanceSetup(0);
        AttendanceSession::factory()->forAssignment($setup['assignment'])->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.assignments.destroy', $setup['assignment']))
            ->assertSessionHas('error');

        $this->assertModelExists($setup['assignment']);
    }

    public function test_an_assignment_without_sessions_can_still_be_removed(): void
    {
        $assignment = TeachingAssignment::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.assignments.destroy', $assignment))
            ->assertSessionHas('success');

        $this->assertModelMissing($assignment);
    }
}
