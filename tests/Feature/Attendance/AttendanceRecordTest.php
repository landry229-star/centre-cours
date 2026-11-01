<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Events\StudentMarkedAbsent;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesAttendance;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    use CreatesAttendance, RefreshDatabase;

    private function makeSession(array $setup, array $attributes = []): AttendanceSession
    {
        return AttendanceSession::factory()->forAssignment($setup['assignment'])->create($attributes);
    }

    public function test_teacher_records_every_status(): void
    {
        $setup = $this->attendanceSetup(4);
        $session = $this->makeSession($setup);
        [$a, $b, $c, $d] = $setup['enrollments']->all();

        $this->actingAs($setup['user'])->put(route('attendance.update', $session), ['entries' => [
            $a->id => ['status' => 'present'],
            $b->id => ['status' => 'late', 'arrived_at' => '08:20'],
            $c->id => ['status' => 'absent', 'note' => 'Sans nouvelles'],
            $d->id => ['status' => 'excused', 'note' => 'Certificat médical'],
        ]])->assertRedirect(route('attendance.show', $session))->assertSessionHas('success');

        $this->assertSame(4, Attendance::count());
        $this->assertSame(AttendanceStatus::Late, Attendance::where('enrollment_id', $b->id)->first()->status);
        $this->assertSame('08:20', Attendance::where('enrollment_id', $b->id)->first()->arrivalTime());
        $this->assertSame('Certificat médical', Attendance::where('enrollment_id', $d->id)->first()->note);
        $this->assertSame($setup['user']->id, Attendance::first()->marked_by);
        $this->assertSame(['present' => 1, 'absent' => 1, 'late' => 1, 'excused' => 1, 'total' => 4], app(AttendanceService::class)->summary($session));
    }

    public function test_resaving_the_sheet_updates_rows_and_never_duplicates(): void
    {
        $setup = $this->attendanceSetup(3);
        $session = $this->makeSession($setup);
        $user = $setup['user'];

        $this->actingAs($user)->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'present'));
        $this->actingAs($user)->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'present'));
        $this->assertSame(3, Attendance::count());

        $this->actingAs($user)->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'absent'));
        $this->assertSame(3, Attendance::count());
        $this->assertSame(3, Attendance::where('status', 'absent')->count());
        $this->assertSame(3, AttendanceCorrection::count());
    }

    public function test_admin_can_correct_a_saved_sheet_and_the_change_is_audited(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $enrollment = $setup['enrollments']->first();
        $admin = User::factory()->admin()->create();

        $this->actingAs($setup['user'])->put(route('attendance.update', $session), ['entries' => [
            $enrollment->id => ['status' => 'late', 'arrived_at' => '08:20', 'note' => 'Bus en retard'],
        ]])->assertRedirect(route('attendance.show', $session));

        $this->actingAs($admin)->put(route('attendance.update', $session), ['entries' => [
            $enrollment->id => ['status' => 'excused', 'note' => 'Justificatif reçu'],
        ]])->assertRedirect(route('attendance.show', $session));

        $attendance = Attendance::firstOrFail();
        $correction = AttendanceCorrection::firstOrFail();

        expect($attendance->status)->toBe(AttendanceStatus::Excused)
            ->and($attendance->arrived_at)->toBeNull()
            ->and($correction->before_status)->toBe(AttendanceStatus::Late)
            ->and($correction->after_status)->toBe(AttendanceStatus::Excused)
            ->and(substr((string) $correction->before_arrived_at, 0, 5))->toBe('08:20')
            ->and($correction->after_arrived_at)->toBeNull()
            ->and($correction->before_note)->toBe('Bus en retard')
            ->and($correction->after_note)->toBe('Justificatif reçu')
            ->and($correction->corrected_by)->toBe($admin->id)
            ->and($correction->corrected_at)->not->toBeNull();

        $this->actingAs($setup['user'])->get(route('attendance.show', $session))
            ->assertOk()
            ->assertSee('Historique des corrections')
            ->assertSee('Justificatif reçu')
            ->assertSee($admin->name);
    }

    public function test_arrival_time_is_dropped_unless_present_or_late(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $enrollment = $setup['enrollments']->first();

        $this->actingAs($setup['user'])->put(route('attendance.update', $session), ['entries' => [
            $enrollment->id => ['status' => 'absent', 'arrived_at' => '08:30'],
        ]])->assertSessionHasNoErrors();

        $this->assertNull(Attendance::first()->arrived_at);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $id = $setup['enrollments']->first()->id;
        $user = $setup['user'];

        $this->actingAs($user)->put(route('attendance.update', $session), ['entries' => [$id => ['status' => 'sleeping']]])
            ->assertSessionHasErrors('entries.'.$id.'.status');
        $this->actingAs($user)->put(route('attendance.update', $session), ['entries' => [$id => ['status' => 'late', 'arrived_at' => 'tard']]])
            ->assertSessionHasErrors('entries.'.$id.'.arrived_at');
        $this->actingAs($user)->put(route('attendance.update', $session), ['entries' => [$id => ['status' => 'absent', 'note' => str_repeat('x', 300)]]])
            ->assertSessionHasErrors('entries.'.$id.'.note');
        $this->actingAs($user)->put(route('attendance.update', $session), [])
            ->assertSessionHasErrors('entries');

        $this->assertSame(0, Attendance::count());
    }

    public function test_a_student_from_another_class_is_refused_and_nothing_is_saved(): void
    {
        $setup = $this->attendanceSetup(1);
        $other = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);

        $this->actingAs($setup['user'])->put(route('attendance.update', $session), ['entries' => [
            $setup['enrollments']->first()->id => ['status' => 'present'],
            $other['enrollments']->first()->id => ['status' => 'present'], // élève d'une autre classe
        ]])->assertSessionHas('error');

        // Tout ou rien : l'élève valide n'a pas été enregistré non plus.
        $this->assertSame(0, Attendance::count());
    }

    public function test_the_roster_depends_on_the_session_date(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup, ['session_date' => now()->subDays(10)->toDateString()]);

        // Arrivé après la séance : absent de la liste.
        Enrollment::factory()->create([
            'student_id' => Student::factory()->create()->id,
            'classroom_id' => $setup['classroom']->id,
            'enrolled_at' => now()->subDays(2)->toDateString(),
        ]);
        // Parti avant la séance : absent de la liste.
        Enrollment::factory()->withdrawn()->create([
            'student_id' => Student::factory()->create()->id,
            'classroom_id' => $setup['classroom']->id,
            'enrolled_at' => now()->subDays(30)->toDateString(),
            'ended_at' => now()->subDays(20)->toDateString(),
        ]);

        $roster = app(AttendanceService::class)->roster($session);

        $this->assertSame([$setup['enrollments']->first()->id], $roster->pluck('id')->all());
    }

    public function test_other_teachers_and_guardians_cannot_touch_the_sheet(): void
    {
        $setup = $this->attendanceSetup(1);
        $other = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $sheet = $this->sheet($setup['enrollments']);

        foreach ([$other['user'], User::factory()->guardian()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)->get(route('attendance.show', $session))->assertForbidden();
            $this->actingAs($user)->put(route('attendance.update', $session), $sheet)->assertForbidden();
            $this->actingAs($user)->post(route('attendance.scan', $session), ['token' => 'x'])->assertForbidden();
        }

        $this->assertSame(0, Attendance::count());
    }

    public function test_an_absence_event_fires_only_when_a_student_becomes_absent(): void
    {
        Event::fake([StudentMarkedAbsent::class]);
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $user = $setup['user'];

        $this->actingAs($user)->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'absent'));
        Event::assertDispatchedTimes(StudentMarkedAbsent::class, 1);

        // Même feuille renvoyée : pas de second message au parent.
        $this->actingAs($user)->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'absent'));
        Event::assertDispatchedTimes(StudentMarkedAbsent::class, 1);

        // Présent puis de nouveau absent : nouvelle absence.
        $this->actingAs($user)->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'present'));
        $this->actingAs($user)->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'absent'));
        Event::assertDispatchedTimes(StudentMarkedAbsent::class, 2);

        // Une absence justifiée n'est pas une « absence » à signaler.
        $this->actingAs($user)->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'excused'));
        Event::assertDispatchedTimes(StudentMarkedAbsent::class, 2);
    }

    public function test_the_sheet_page_shows_saved_statuses(): void
    {
        $setup = $this->attendanceSetup(1);
        $session = $this->makeSession($setup);
        $this->actingAs($setup['user'])->put(route('attendance.update', $session), $this->sheet($setup['enrollments'], 'absent'));

        $this->actingAs($setup['user'])->get(route('attendance.show', $session))
            ->assertOk()
            ->assertSee('1 absent(s)');
    }
}
