<?php

namespace Tests\Feature\Attendance;

use App\Enums\ClassroomStatus;
use App\Http\Controllers\Web\AttendanceController;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesAttendance;
use Tests\TestCase;

class AttendanceSessionTest extends TestCase
{
    use CreatesAttendance, RefreshDatabase;

    private function payload(array $setup, array $overrides = []): array
    {
        return array_merge([
            'session_type' => 'lesson',
            'assignment_id' => $setup['assignment']->id,
            'date' => now()->toDateString(),
            'starts_at' => '08:00',
        ], $overrides);
    }

    public function test_teacher_opens_a_session_for_their_own_assignment(): void
    {
        $setup = $this->attendanceSetup();

        $response = $this->actingAs($setup['user'])->post(route('attendance.store'), $this->payload($setup));

        $session = AttendanceSession::firstOrFail();
        $response->assertRedirect(route('attendance.show', $session));
        $this->assertSame($setup['classroom']->id, $session->classroom_id);
        $this->assertSame($setup['subject']->id, $session->subject_id);
        $this->assertSame($setup['teacher']->id, $session->teacher_id);
        $this->assertSame($setup['user']->id, $session->created_by);
    }

    public function test_teacher_opens_one_daily_call_per_class_and_date(): void
    {
        $setup = $this->attendanceSetup();
        $payload = $this->payload($setup, ['session_type' => 'daily']);
        unset($payload['starts_at']);

        $this->actingAs($setup['user'])
            ->post(route('attendance.store'), $payload)
            ->assertSessionHasNoErrors();

        $dailySession = AttendanceSession::firstOrFail();
        $this->assertSame('daily', $dailySession->session_type->value);
        $this->assertNull($dailySession->subject_id);
        $this->assertSame('00:00', $dailySession->startTime());

        $this->actingAs($setup['user'])
            ->post(route('attendance.store'), array_diff_key(
                $this->payload($setup, ['session_type' => 'daily']),
                ['starts_at' => true],
            ))
            ->assertRedirect(route('attendance.show', $dailySession))
            ->assertSessionHas('success', 'Cet appel quotidien existe déjà : le voici.');

        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_any_teacher_assigned_to_the_class_can_view_its_daily_call_but_not_another_subject_session(): void
    {
        $setup = $this->attendanceSetup();
        $otherUser = User::factory()->teacher()->create();
        $otherTeacher = Teacher::factory()->create(['user_id' => $otherUser->id]);
        TeachingAssignment::factory()->create([
            'classroom_id' => $setup['classroom']->id,
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $otherTeacher->id,
        ]);
        $dailyCall = AttendanceSession::factory()->forAssignment($setup['assignment'])->create([
            'session_type' => 'daily',
            'subject_id' => null,
            'starts_at' => '00:00:00',
        ]);
        $lesson = AttendanceSession::factory()->forAssignment($setup['assignment'])->create();

        $this->actingAs($otherUser)
            ->get(route('attendance.show', $dailyCall))
            ->assertOk();

        $this->actingAs($otherUser)
            ->get(route('attendance.show', $lesson))
            ->assertForbidden();
    }

    public function test_admin_can_open_a_session_for_any_assignment(): void
    {
        $setup = $this->attendanceSetup();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('attendance.store'), $this->payload($setup))->assertRedirect();

        // La séance reste attribuée à l'enseignant de la matière, pas à l'admin qui l'a ouverte.
        $this->assertSame($setup['teacher']->id, AttendanceSession::firstOrFail()->teacher_id);
    }

    public function test_a_teacher_cannot_open_a_session_for_someone_elses_assignment(): void
    {
        $mine = $this->attendanceSetup();
        $theirs = $this->attendanceSetup();

        $this->actingAs($mine['user'])->post(route('attendance.store'), $this->payload($theirs))->assertForbidden();

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_reopening_the_same_slot_never_creates_a_duplicate(): void
    {
        $setup = $this->attendanceSetup();
        $this->actingAs($setup['user'])->post(route('attendance.store'), $this->payload($setup));
        $first = AttendanceSession::firstOrFail();

        $this->actingAs($setup['user'])->post(route('attendance.store'), $this->payload($setup))
            ->assertRedirect(route('attendance.show', $first))
            ->assertSessionHas('success', 'Cette séance existe déjà : la voici.');

        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_another_start_time_is_another_session(): void
    {
        $setup = $this->attendanceSetup();
        $this->actingAs($setup['user'])->post(route('attendance.store'), $this->payload($setup));
        $this->actingAs($setup['user'])->post(route('attendance.store'), $this->payload($setup, ['starts_at' => '10:00']));

        $this->assertSame(2, AttendanceSession::count());
    }

    public function test_invalid_dates_and_times_are_rejected(): void
    {
        $setup = $this->attendanceSetup();
        $teacher = $setup['user'];

        $this->actingAs($teacher)->post(route('attendance.store'), $this->payload($setup, ['date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('date');
        $this->actingAs($teacher)->post(route('attendance.store'), $this->payload($setup, ['starts_at' => '8h']))
            ->assertSessionHasErrors('starts_at');
        $this->actingAs($teacher)->post(route('attendance.store'), $this->payload($setup, ['ends_at' => '07:00']))
            ->assertSessionHasErrors('ends_at');
        $this->actingAs($teacher)->post(route('attendance.store'), $this->payload($setup, ['assignment_id' => 999999]))
            ->assertSessionHasErrors('assignment_id');

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_a_date_outside_the_school_year_is_refused(): void
    {
        $setup = $this->attendanceSetup(); // l'année commence il y a 2 mois

        $this->actingAs($setup['user'])
            ->post(route('attendance.store'), $this->payload($setup, ['date' => now()->subMonths(6)->toDateString()]))
            ->assertSessionHas('error');

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_a_closed_classroom_cannot_take_attendance(): void
    {
        $setup = $this->attendanceSetup();
        $setup['classroom']->forceFill(['status' => ClassroomStatus::Closed])->save();

        $this->actingAs($setup['user'])->post(route('attendance.store'), $this->payload($setup))
            ->assertSessionHas('error');

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_guardian_and_student_have_no_access_to_attendance_screens(): void
    {
        $setup = $this->attendanceSetup();

        foreach ([User::factory()->guardian()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)->get(route('attendance.index'))->assertForbidden();
            $this->actingAs($user)->get(route('attendance.create'))->assertForbidden();
            $this->actingAs($user)->post(route('attendance.store'), $this->payload($setup))->assertForbidden();
        }

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_a_teacher_without_profile_sees_nothing(): void
    {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('attendance.index'))->assertForbidden();
    }

    public function test_the_list_only_shows_the_teachers_own_sessions(): void
    {
        $mine = $this->attendanceSetup();
        $theirs = $this->attendanceSetup();
        // Noms distincts et sans lien de sous-chaîne (assertSee cherche du texte brut).
        $mine['classroom']->forceFill(['name' => 'Classe Alpha'])->save();
        $theirs['classroom']->forceFill(['name' => 'Classe Beta'])->save();
        AttendanceSession::factory()->forAssignment($mine['assignment'])->create();
        AttendanceSession::factory()->forAssignment($theirs['assignment'])->create();

        $this->actingAs($mine['user'])->get(route('attendance.index'))
            ->assertOk()
            ->assertSee($mine['classroom']->name)
            ->assertDontSee($theirs['classroom']->name);

        $this->actingAs(User::factory()->admin()->create())->get(route('attendance.index'))
            ->assertOk()
            ->assertSee($mine['classroom']->name)
            ->assertSee($theirs['classroom']->name);
    }

    public function test_attendance_list_filters_by_date_class_and_distinct_status_with_totals(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $setup = $this->attendanceSetup(2);
        $older = AttendanceSession::factory()->forAssignment($setup['assignment'])->create([
            'session_date' => now()->subDays(5)->toDateString(),
        ]);
        $recent = AttendanceSession::factory()->forAssignment($setup['assignment'])->create([
            'session_date' => now()->subDay()->toDateString(),
        ]);
        [$excusedEnrollment, $absentEnrollment] = $setup['enrollments']->all();
        Attendance::factory()->create([
            'attendance_session_id' => $older->id,
            'classroom_id' => $older->classroom_id,
            'enrollment_id' => $excusedEnrollment->id,
            'status' => 'excused',
            'marked_by' => $setup['user']->id,
        ]);
        Attendance::factory()->create([
            'attendance_session_id' => $recent->id,
            'classroom_id' => $recent->classroom_id,
            'enrollment_id' => $absentEnrollment->id,
            'status' => 'absent',
            'marked_by' => $setup['user']->id,
        ]);

        $this->actingAs($setup['user'])
            ->get(route('attendance.index', [
                'classroom_id' => $setup['classroom']->id,
                'from' => now()->subDays(2)->toDateString(),
                'to' => now()->toDateString(),
                'status' => 'absent',
            ]))
            ->assertOk()
            ->assertSee(route('attendance.show', $recent))
            ->assertDontSee(route('attendance.show', $older))
            ->assertSee('1 séance(s) · 1 pointage(s)')
            ->assertSee('Absent : 1')
            ->assertSee('Absence justifiée : 0');
    }

    public function test_teacher_csv_export_includes_only_visible_matching_attendance_rows(): void
    {
        $mine = $this->attendanceSetup(0);
        $other = $this->attendanceSetup(0);
        $mineSession = AttendanceSession::factory()->forAssignment($mine['assignment'])->create();
        $otherSession = AttendanceSession::factory()->forAssignment($other['assignment'])->create();

        foreach ([[$mine, $mineSession, 'ExportVisible'], [$other, $otherSession, 'ExportInterdit']] as [$setup, $session, $firstName]) {
            $enrollment = Enrollment::factory()->create([
                'student_id' => Student::factory()->create(['first_name' => $firstName])->id,
                'classroom_id' => $setup['classroom']->id,
            ]);
            Attendance::factory()->create([
                'attendance_session_id' => $session->id,
                'classroom_id' => $session->classroom_id,
                'enrollment_id' => $enrollment->id,
                'status' => 'excused',
                'marked_by' => $setup['user']->id,
            ]);
        }

        $request = Request::create('/presences/export', 'GET', ['status' => 'excused']);
        $request->setUserResolver(fn () => $mine['user']);
        $this->actingAs($mine['user']);
        $response = app(AttendanceController::class)->export($request);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        expect($csv)
            ->toContain('ExportVisible')
            ->toContain($mineSession->session_date->toDateString())
            ->toContain('Absence justifiée')
            ->not->toContain('ExportInterdit');
    }

    public function test_pages_render(): void
    {
        $setup = $this->attendanceSetup(2);
        $session = AttendanceSession::factory()->forAssignment($setup['assignment'])->create();

        $this->actingAs($setup['user'])->get(route('attendance.create'))->assertOk()->assertSee($setup['subject']->name);
        $this->actingAs($setup['user'])->get(route('attendance.show', $session))
            ->assertOk()
            ->assertSee("Feuille d'appel", false)
            ->assertSee($setup['enrollments']->first()->student->matricule);
    }

    public function test_a_teacher_cannot_open_another_teachers_sheet(): void
    {
        $mine = $this->attendanceSetup();
        $theirs = $this->attendanceSetup();
        $session = AttendanceSession::factory()->forAssignment($theirs['assignment'])->create();

        $this->actingAs($mine['user'])->get(route('attendance.show', $session))->assertForbidden();
    }

    public function test_teacher_keeps_access_to_old_sessions_after_a_reassignment_and_the_new_teacher_gets_access(): void
    {
        $old = $this->attendanceSetup();
        $session = AttendanceSession::factory()->forAssignment($old['assignment'])->create();

        $newTeacherUser = User::factory()->teacher()->create();
        $newTeacher = Teacher::factory()->create(['user_id' => $newTeacherUser->id]);
        $old['assignment']->forceFill(['teacher_id' => $newTeacher->id])->save();

        $this->actingAs($old['user'])->get(route('attendance.show', $session))->assertOk();
        $this->actingAs($newTeacherUser)->get(route('attendance.show', $session))->assertOk();
    }
}
