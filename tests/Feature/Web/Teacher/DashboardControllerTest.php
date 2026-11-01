<?php

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Level;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;

it('shows only recently entered grades belonging to the signed in teacher', function () {
    $this->travelTo('2026-10-04 12:00:00');

    $schoolYear = SchoolYear::factory()->active()->create([
        'label' => '2026-2027',
        'start_date' => '2026-08-01',
        'end_date' => '2027-05-31',
    ]);
    $admin = User::factory()->admin()->create();
    $teacherUser = User::factory()->teacher()->create();
    $otherTeacherUser = User::factory()->teacher()->create();
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    $otherTeacher = Teacher::factory()->create(['user_id' => $otherTeacherUser->id]);
    $classroom = Classroom::factory()->create([
        'school_year_id' => $schoolYear->id,
        'level_id' => Level::factory()->create()->id,
    ]);
    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'enrolled_at' => '2026-08-01',
    ]);
    $subject = Subject::factory()->create();
    $otherSubject = Subject::factory()->create();
    $assignment = $classroom->assignments()->create([
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'coefficient' => 1,
    ]);
    $otherAssignment = $classroom->assignments()->create([
        'subject_id' => $otherSubject->id,
        'teacher_id' => $otherTeacher->id,
        'coefficient' => 1,
    ]);
    $ownEvaluation = Evaluation::factory()->forAssignment($assignment)->create([
        'evaluation_date' => '2026-10-03',
        'created_by' => $admin->id,
    ]);
    $otherAuthorEvaluation = Evaluation::factory()->forAssignment($assignment)->create([
        'evaluation_date' => '2026-10-03',
        'created_by' => $admin->id,
    ]);
    $otherTeacherEvaluation = Evaluation::factory()->forAssignment($otherAssignment)->create([
        'evaluation_date' => '2026-10-03',
        'created_by' => $admin->id,
    ]);
    $ownGrade = Grade::factory()->forEvaluation($ownEvaluation, $enrollment)->create([
        'entered_by' => $teacherUser->id,
    ]);
    Grade::factory()->forEvaluation($otherAuthorEvaluation, $enrollment)->create([
        'entered_by' => $otherTeacherUser->id,
    ]);
    Grade::factory()->forEvaluation($otherTeacherEvaluation, $enrollment)->create([
        'entered_by' => $teacherUser->id,
    ]);

    $this->actingAs($teacherUser)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertViewHas('recentGrades', fn ($grades): bool => $grades->modelKeys() === [$ownGrade->id])
        ->assertSeeText('Dernières notes saisies');
});
