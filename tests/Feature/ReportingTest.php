<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\FeeDueDate;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\Level;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-04 12:00:00');
});

function schoolYearReportingSetup(): array
{
    $schoolYear = SchoolYear::factory()->active()->create([
        'label' => '2026-2027',
        'start_date' => '2026-08-01',
        'end_date' => '2027-05-31',
    ]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create([
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'name' => '6e A',
    ]);
    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'enrolled_at' => '2026-08-01',
    ]);
    $admin = User::factory()->admin()->create();
    $teacher = Teacher::factory()->create();
    $subject = Subject::factory()->create();
    $assignment = $classroom->assignments()->create([
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'coefficient' => 1,
    ]);
    $session = AttendanceSession::factory()->forAssignment($assignment)->create([
        'session_date' => '2026-10-02',
    ]);
    Attendance::factory()->create([
        'attendance_session_id' => $session->id,
        'classroom_id' => $classroom->id,
        'enrollment_id' => $enrollment->id,
        'status' => AttendanceStatus::Absent,
        'marked_by' => $admin->id,
    ]);
    $evaluation = Evaluation::factory()->forAssignment($assignment)->create([
        'evaluation_date' => '2026-10-03',
        'published_at' => now(),
        'published_by' => $admin->id,
        'created_by' => $admin->id,
    ]);
    Grade::factory()->forEvaluation($evaluation, $enrollment)->create([
        'score' => 16,
        'entered_by' => $admin->id,
    ]);
    $weightedEvaluation = Evaluation::factory()->forAssignment($assignment)->create([
        'evaluation_date' => '2026-10-03',
        'coefficient' => 3,
        'published_at' => now(),
        'published_by' => $admin->id,
        'created_by' => $admin->id,
    ]);
    Grade::factory()->forEvaluation($weightedEvaluation, $enrollment)->create([
        'score' => 10,
        'entered_by' => $admin->id,
    ]);
    $unpublishedEvaluation = Evaluation::factory()->forAssignment($assignment)->create([
        'evaluation_date' => '2026-10-03',
        'published_at' => null,
        'created_by' => $admin->id,
    ]);
    Grade::factory()->forEvaluation($unpublishedEvaluation, $enrollment)->create([
        'score' => 2,
        'entered_by' => $admin->id,
    ]);
    FeeSchedule::factory()->create([
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'monthly_amount' => 10000,
    ]);
    foreach (['2026-08-10', '2026-09-10', '2026-10-01'] as $dueDate) {
        FeeDueDate::factory()->create([
            'student_id' => $student->id,
            'school_year_id' => $schoolYear->id,
            'month' => Carbon::parse($dueDate)->startOfMonth()->toDateString(),
            'due_date' => $dueDate,
        ]);
    }
    Payment::factory()->create([
        'enrollment_id' => $enrollment->id,
        'month' => '2026-10-01',
        'paid_at' => '2026-10-03',
        'amount' => 5000,
        'recorded_by' => $admin->id,
    ]);

    return compact('schoolYear', 'classroom', 'student', 'enrollment', 'admin', 'evaluation');
}

it('renders year period class and financial reporting for administrators', function () {
    $setup = schoolYearReportingSetup();

    $this->actingAs($setup['admin'])
        ->get(route('admin.reports.index', [
            'school_year_id' => $setup['schoolYear']->id,
            'classroom_id' => $setup['classroom']->id,
            'period' => 'annual:1',
        ]))
        ->assertOk()
        ->assertViewHas('report', function (array $report): bool {
            $class = $report['classes']->first();

            return $class['name'] === '6e A'
                && $class['enrollments'] === 1
                && $class['attendance'][AttendanceStatus::Absent->value] === 1
                && $class['grades_average'] === 11.5
                && $class['grades_count'] === 2
                && $class['grades_students'] === 1
                && $class['expected_due'] === 30000
                && $class['paid_due'] === 5000
                && $class['remaining_due'] === 25000
                && $report['totals']['students_without_fee_schedule'] === 0;
        })
        ->assertSeeText('Synthèse de pilotage');
});

it('exports only the selected year and class as csv', function () {
    $setup = schoolYearReportingSetup();
    $setup['classroom']->update(['name' => '=HYPERLINK("https://example.invalid")']);
    $otherYear = SchoolYear::factory()->create([
        'label' => '2025-2026',
        'start_date' => '2025-08-01',
        'end_date' => '2026-05-31',
    ]);
    Classroom::factory()->create([
        'school_year_id' => $otherYear->id,
        'name' => 'Classe hors filtre',
    ]);

    $response = $this->actingAs($setup['admin'])
        ->get(route('admin.reports.export', [
            'school_year_id' => $setup['schoolYear']->id,
            'classroom_id' => $setup['classroom']->id,
            'period' => 'annual:1',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain($setup['schoolYear']->label)
        ->toContain('Année complète')
        ->toContain('\'=HYPERLINK')
        ->toContain('25000')
        ->not->toContain('Classe hors filtre');
});

it('forbids non-administrators from accessing school reporting', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get(route('admin.reports.index'))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->get(route('admin.reports.export'))
        ->assertForbidden();
});

it('shows a setup prompt when no school year exists', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSeeText('Aucune année scolaire disponible')
        ->assertSee(route('admin.school-years.create'));
});

it('rejects a classroom filter from a different school year', function () {
    $setup = schoolYearReportingSetup();
    $otherYear = SchoolYear::factory()->create([
        'label' => '2025-2026',
        'start_date' => '2025-08-01',
        'end_date' => '2026-05-31',
    ]);
    $otherClassroom = Classroom::factory()->create(['school_year_id' => $otherYear->id]);

    $this->actingAs($setup['admin'])
        ->get(route('admin.reports.index', [
            'school_year_id' => $setup['schoolYear']->id,
            'classroom_id' => $otherClassroom->id,
        ]))
        ->assertSessionHasErrors('classroom_id');
});

it('keeps reporting query counts bounded with a representative class volume', function () {
    $setup = schoolYearReportingSetup();
    Enrollment::factory()->count(100)->create([
        'classroom_id' => $setup['classroom']->id,
        'school_year_id' => $setup['schoolYear']->id,
        'enrolled_at' => '2026-08-01',
    ]);
    $queryCount = 0;
    DB::listen(function (QueryExecuted $query) use (&$queryCount): void {
        $queryCount++;
    });

    $startedAt = microtime(true);
    $this->actingAs($setup['admin'])
        ->get(route('admin.reports.index', [
            'school_year_id' => $setup['schoolYear']->id,
            'classroom_id' => $setup['classroom']->id,
        ]))
        ->assertOk()
        ->assertViewHas('report.totals.enrollments', 101);
    $durationSeconds = microtime(true) - $startedAt;

    expect($queryCount)->toBeLessThanOrEqual(40);
    expect($durationSeconds)->toBeLessThanOrEqual(3.0);
});
