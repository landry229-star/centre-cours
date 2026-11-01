<?php

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Guardian;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Grades\GradebookService;
use App\Services\Grades\ReportCardService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function gradePublicationSetup(): array
{
    $schoolYear = SchoolYear::factory()->active()->create([
        'start_date' => '2030-08-01',
        'end_date' => '2031-05-31',
    ]);
    $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'enrolled_at' => '2030-08-01',
    ]);
    $teacherUser = User::factory()->teacher()->create();
    $teacher = Teacher::factory()->create();
    $teacher->forceFill(['user_id' => $teacherUser->id])->save();
    $assignment = $classroom->assignments()->create([
        'subject_id' => Subject::factory()->create()->id,
        'teacher_id' => $teacher->id,
        'coefficient' => 2,
    ]);
    $admin = User::factory()->admin()->create();
    $evaluation = app(GradebookService::class)->createEvaluation($assignment, [
        'title' => 'Contrôle de rentrée',
        'type' => 'quiz',
        'evaluation_date' => '2030-10-15',
        'coefficient' => 1,
    ], $admin);

    return compact('schoolYear', 'classroom', 'student', 'enrollment', 'teacherUser', 'teacher', 'assignment', 'admin', 'evaluation');
}

it('allows only the owner or an administrator to publish an evaluation', function () {
    $setup = gradePublicationSetup();
    $otherTeacherUser = User::factory()->teacher()->create();
    $otherTeacher = Teacher::factory()->create(['user_id' => $otherTeacherUser->id]);
    $service = app(GradebookService::class);

    expect(fn () => $service->publishEvaluation($setup['evaluation'], $otherTeacherUser))
        ->toThrow(DomainException::class);
    expect(fn () => $service->recordGrades($setup['evaluation'], [
        $setup['enrollment']->id => ['score' => 12],
    ], $otherTeacherUser))->toThrow(DomainException::class);
    expect(Grade::query()->count())->toBe(0);

    $published = $service->publishEvaluation($setup['evaluation'], $setup['teacherUser']);

    expect($published->published_at)->not->toBeNull()
        ->and($published->published_by)->toBe($setup['teacherUser']->id)
        ->and($otherTeacher->id)->not->toBe($setup['teacher']->id);
});

it('records every published grade correction with before and after values, author, date, and reason', function () {
    $setup = gradePublicationSetup();
    $service = app(GradebookService::class);
    $service->recordGrades($setup['evaluation'], [
        $setup['enrollment']->id => ['score' => 12, 'comment' => 'Appréciation conservée'],
    ], $setup['teacherUser']);
    $service->publishEvaluation($setup['evaluation'], $setup['teacherUser']);
    $grade = Grade::query()->firstOrFail();

    $correction = $service->correctPublishedGrade($grade, 15.5, 'Erreur de saisie vérifiée', $setup['admin']);

    expect($grade->fresh()->score)->toBe('15.50')
        ->and($grade->fresh()->comment)->toBe('Appréciation conservée')
        ->and($correction)->toBeInstanceOf(GradeCorrection::class);
    $this->assertDatabaseHas('grade_corrections', [
        'grade_id' => $grade->id,
        'corrected_by' => $setup['admin']->id,
        'previous_score' => 12,
        'new_score' => 15.5,
        'reason' => 'Erreur de saisie vérifiée',
    ]);

    expect(fn () => $service->correctPublishedGrade($grade, 18, 'Tentative non autorisée', $setup['teacherUser']))
        ->toThrow(DomainException::class);
    expect(fn () => $service->recordGrades($setup['evaluation'], [
        $setup['enrollment']->id => ['score' => 18],
    ], $setup['admin']))->toThrow(DomainException::class);
});

it('hides unpublished grades from a guardian until publication and preserves the per-grade appreciation', function () {
    $setup = gradePublicationSetup();
    $guardianUser = User::factory()->guardian()->create();
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $setup['student']->forceFill(['guardian_id' => $guardian->id])->save();
    $studentUser = User::factory()->student()->create();
    $setup['student']->forceFill(['user_id' => $studentUser->id])->save();
    app(GradebookService::class)->recordGrades($setup['evaluation'], [
        $setup['enrollment']->id => ['score' => 17, 'comment' => 'Très bonne maîtrise'],
    ], $setup['teacherUser']);

    $this->actingAs($guardianUser)
        ->get(route('guardian.grades.index'))
        ->assertDontSee('17,00');
    $this->actingAs($studentUser)
        ->get(route('student.grades.index'))
        ->assertDontSee('17,00');
    expect(app(GradebookService::class)->summariesForStudent($setup['student']))->toBeEmpty();
    $this->actingAs($guardianUser)
        ->get(route('report-cards.index'))
        ->assertDontSeeText($setup['student']->full_name);
    $this->actingAs($guardianUser)
        ->get(route('report-cards.show', $setup['enrollment']))
        ->assertNotFound();
    expect(app(ReportCardService::class)->make($setup['enrollment'], 'annual', 1)['grades'])->toBeEmpty();

    app(GradebookService::class)->publishEvaluation($setup['evaluation'], $setup['teacherUser']);

    $this->actingAs($guardianUser)
        ->get(route('report-cards.index'))
        ->assertSeeText($setup['student']->full_name);
    $this->actingAs($guardianUser)
        ->get(route('guardian.grades.index'))
        ->assertSee('17,00');
    $this->actingAs($studentUser)
        ->get(route('student.grades.index'))
        ->assertSee('17,00');
    $report = app(ReportCardService::class)->make($setup['enrollment'], 'annual', 1);

    expect(app(GradebookService::class)->summariesForStudent($setup['student']))->not->toBeEmpty()
        ->and($report['grades'])->toHaveCount(1)
        ->and($report['grades']->first()->comment)->toBe('Très bonne maîtrise');
});
