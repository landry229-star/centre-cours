<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceSessionType;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAttendance;
use Tests\TestCase;

class QrAttendanceTest extends TestCase
{
    use CreatesAttendance, RefreshDatabase;

    private function makeSession(array $setup, array $attributes = []): AttendanceSession
    {
        // Séance du jour à 08:00.
        return AttendanceSession::factory()->forAssignment($setup['assignment'])->create($attributes);
    }

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse(now()->toDateString().' '.$time));
    }

    public function test_a_scan_on_time_marks_present_with_the_arrival_time(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $student = $setup['enrollments']->first()->student;
        $this->at('08:05');

        $this->actingAs($setup['user'])->post(route('attendance.scan', $session), ['token' => $student->qr_token])
            ->assertRedirect()->assertSessionHas('success');

        $attendance = Attendance::firstOrFail();
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame('08:05', $attendance->arrivalTime());
    }

    public function test_a_scan_after_the_grace_period_marks_late(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $this->at('08:30'); // tolérance par défaut : 10 minutes

        $this->actingAs($setup['user'])->post(route('attendance.scan', $session), ['token' => $setup['enrollments']->first()->student->qr_token]);

        $this->assertSame(AttendanceStatus::Late, Attendance::firstOrFail()->status);
    }

    public function test_scanning_a_daily_call_never_marks_a_student_late(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = app(AttendanceService::class)->openSession(
            $setup['assignment'],
            AttendanceSessionType::Daily,
            now(),
            '00:00',
            null,
            $setup['user'],
        );
        $this->at('08:30');

        $this->actingAs($setup['user'])
            ->post(route('attendance.scan', $session), ['token' => $setup['enrollments']->first()->student->qr_token]);

        $this->assertSame(AttendanceStatus::Present, Attendance::firstOrFail()->status);
    }

    public function test_scanning_a_past_session_is_never_late(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup, ['session_date' => now()->subDay()->toDateString()]);
        $this->at('15:00');

        $this->actingAs($setup['user'])->post(route('attendance.scan', $session), ['token' => $setup['enrollments']->first()->student->qr_token]);

        $this->assertSame(AttendanceStatus::Present, Attendance::firstOrFail()->status);
    }

    public function test_scanning_twice_changes_nothing_and_says_so(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $token = $setup['enrollments']->first()->student->qr_token;
        $this->at('08:02');
        $this->actingAs($setup['user'])->post(route('attendance.scan', $session), ['token' => $token]);
        $this->at('08:40');

        $this->actingAs($setup['user'])->postJson(route('attendance.scan', $session), ['token' => $token])
            ->assertOk()
            ->assertJson(['ok' => true, 'already' => true, 'status' => 'present']);

        $this->assertSame(1, Attendance::count());
        $this->assertSame(AttendanceStatus::Present, Attendance::first()->status); // pas passé en retard
    }

    public function test_a_scan_overrides_a_manual_absence(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $enrollment = $setup['enrollments']->first();
        $this->actingAs($setup['user'])->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'absent'));
        $this->at('08:03');

        $this->actingAs($setup['user'])->post(route('attendance.scan', $session), ['token' => $enrollment->student->qr_token]);

        $this->assertSame(1, Attendance::count());
        $this->assertSame(AttendanceStatus::Present, Attendance::first()->status);
    }

    public function test_an_unknown_token_is_refused(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);

        $this->actingAs($setup['user'])->post(route('attendance.scan', $session), ['token' => 'jeton-invente'])
            ->assertSessionHas('error', 'QR Code inconnu.');
        $this->actingAs($setup['user'])->postJson(route('attendance.scan', $session), ['token' => 'jeton-invente'])
            ->assertStatus(422)->assertJson(['ok' => false]);
        $this->actingAs($setup['user'])->postJson(route('attendance.scan', $session), [])->assertStatus(422);

        $this->assertSame(0, Attendance::count());
    }

    public function test_a_student_from_another_class_is_refused(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $stranger = Student::factory()->create();

        $this->actingAs($setup['user'])->postJson(route('attendance.scan', $session), ['token' => $stranger->qr_token])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertSame(0, Attendance::count());
    }

    public function test_json_scan_returns_what_the_sheet_needs_to_refresh(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $enrollment = $setup['enrollments']->first();
        $this->at('08:01');

        $this->actingAs($setup['user'])->postJson(route('attendance.scan', $session), ['token' => $enrollment->student->qr_token])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'present', 'enrollment_id' => $enrollment->id, 'already' => false]);
    }

    public function test_the_admin_can_scan_too(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('attendance.scan', $session), ['token' => $setup['enrollments']->first()->student->qr_token])
            ->assertSessionHas('success');

        $this->assertSame(1, Attendance::count());
    }
}
