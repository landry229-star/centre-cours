<?php

namespace Tests\Feature\Grades;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Grades\GradebookService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GradeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_evaluations_records_grades_and_sees_weighted_class_ranking(): void
    {
        $setup = $this->gradeSetup();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('grades.store'), [
            'assignment_id' => $setup['assignment']->id,
            'title' => 'Interrogation 1',
            'type' => 'quiz',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
        ])->assertRedirect();

        $firstEvaluation = Evaluation::query()->firstOrFail();
        $this->actingAs($admin)->put(route('grades.update', $firstEvaluation), [
            'entries' => [
                $setup['enrollments'][0]->id => ['score' => '10', 'comment' => ''],
                $setup['enrollments'][1]->id => ['score' => '10', 'comment' => ''],
            ],
        ])->assertRedirect(route('grades.show', $firstEvaluation));

        $service = app(GradebookService::class);
        $secondEvaluation = $service->createEvaluation($setup['assignment'], [
            'title' => 'Composition',
            'type' => 'composition',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 3,
        ], $admin);
        $service->recordGrades($secondEvaluation, [
            $setup['enrollments'][0]->id => ['score' => 18],
            $setup['enrollments'][1]->id => ['score' => 12],
        ], $admin);

        $secondAssignment = $setup['classroom']->assignments()->create([
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $setup['teacher']->id,
            'coefficient' => 1,
        ]);
        $otherSubjectEvaluation = $service->createEvaluation($secondAssignment, [
            'title' => 'Devoir de français',
            'type' => 'homework',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
        ], $admin);
        $service->recordGrades($otherSubjectEvaluation, [
            $setup['enrollments'][0]->id => ['score' => 20],
            $setup['enrollments'][1]->id => ['score' => 10],
        ], $admin);

        $ranking = $service->ranking($setup['classroom'], $setup['year']);

        $this->assertSame(17.33, $ranking[0]['average']);
        $this->assertSame(1, $ranking[0]['rank']);
        $this->assertSame(11.0, $ranking[1]['average']);
        $this->assertSame(2, $ranking[1]['rank']);
        $this->assertDatabaseHas('evaluations', [
            'id' => $secondEvaluation->id,
            'subject_coefficient' => 2,
        ]);
        $this->assertDatabaseHas('grades', [
            'evaluation_id' => $firstEvaluation->id,
            'enrollment_id' => $setup['enrollments'][0]->id,
            'score' => 10,
        ]);
    }

    public function test_teacher_can_only_create_evaluations_for_their_own_assignment(): void
    {
        $setup = $this->gradeSetup();
        $other = $this->gradeSetup(0, $setup['year']);

        $this->actingAs($setup['teacherUser'])->post(route('grades.store'), [
            'assignment_id' => $other['assignment']->id,
            'title' => 'Évaluation non autorisée',
            'type' => 'exam',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
        ])->assertSessionHas('error');

        $this->assertSame(0, Evaluation::count());
    }

    public function test_guardians_and_students_cannot_open_the_teacher_gradebook(): void
    {
        foreach ([
            User::factory()->guardian()->create(),
            User::factory()->student()->create(),
        ] as $user) {
            $this->actingAs($user)->get(route('grades.index'))->assertForbidden();
        }
    }

    public function test_grade_must_be_between_zero_and_twenty(): void
    {
        $setup = $this->gradeSetup();
        $admin = User::factory()->admin()->create();
        $evaluation = app(GradebookService::class)->createEvaluation($setup['assignment'], [
            'title' => 'Test',
            'type' => 'quiz',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
        ], $admin);

        $this->actingAs($admin)
            ->put(route('grades.update', $evaluation), [
                'entries' => [$setup['enrollments'][0]->id => ['score' => '20.01']],
            ])
            ->assertSessionHasErrors('entries.'.$setup['enrollments'][0]->id.'.score');

        $this->assertSame(0, Grade::count());
    }

    public function test_database_rejects_a_grade_above_twenty(): void
    {
        $setup = $this->gradeSetup(1);
        $admin = User::factory()->admin()->create();
        $evaluation = app(GradebookService::class)->createEvaluation($setup['assignment'], [
            'title' => 'Test',
            'type' => 'quiz',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
        ], $admin);

        $this->expectException(QueryException::class);

        DB::table('grades')->insert([
            'evaluation_id' => $evaluation->id,
            'enrollment_id' => $setup['enrollments'][0]->id,
            'classroom_id' => $setup['classroom']->id,
            'entered_by' => $admin->id,
            'score' => 20.01,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_grade_entry_rejects_an_enrollment_from_another_class(): void
    {
        $setup = $this->gradeSetup(1);
        $otherClassroom = Classroom::factory()->create(['school_year_id' => $setup['year']->id]);
        $foreignEnrollment = Enrollment::factory()->create([
            'classroom_id' => $otherClassroom->id,
            'school_year_id' => $setup['year']->id,
            'enrolled_at' => now()->subDays(2)->toDateString(),
        ]);
        $admin = User::factory()->admin()->create();
        $evaluation = app(GradebookService::class)->createEvaluation($setup['assignment'], [
            'title' => 'Test',
            'type' => 'quiz',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
        ], $admin);

        $this->actingAs($admin)
            ->put(route('grades.update', $evaluation), [
                'entries' => [$foreignEnrollment->id => ['score' => '15']],
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Grade::count());
    }

    public function test_guardian_and_student_only_see_their_own_grades(): void
    {
        $setup = $this->gradeSetup();
        [$owner, $stranger] = [
            User::factory()->guardian()->create(),
            User::factory()->guardian()->create(),
        ];
        $ownerProfile = Guardian::factory()->create();
        $ownerProfile->forceFill(['user_id' => $owner->id])->save();
        $strangerProfile = Guardian::factory()->create();
        $strangerProfile->forceFill(['user_id' => $stranger->id])->save();

        $myStudent = $setup['enrollments'][0]->student;
        $myStudent->forceFill(['guardian_id' => $ownerProfile->id])->save();
        $myStudent->forceFill(['first_name' => 'Visible'])->save();
        $otherStudent = $setup['enrollments'][1]->student;
        $otherStudent->forceFill(['guardian_id' => $strangerProfile->id])->save();
        $otherStudent->forceFill(['first_name' => 'Prive'])->save();

        $admin = User::factory()->admin()->create();
        $evaluation = app(GradebookService::class)->createEvaluation($setup['assignment'], [
            'title' => 'Test',
            'type' => 'quiz',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
        ], $admin);
        app(GradebookService::class)->recordGrades($evaluation, [
            $setup['enrollments'][0]->id => ['score' => '15'],
            $setup['enrollments'][1]->id => ['score' => '12'],
        ], $admin);
        app(GradebookService::class)->publishEvaluation($evaluation, $admin);

        $this->actingAs($owner)
            ->get(route('guardian.grades.index'))
            ->assertOk()
            ->assertSee('Visible')
            ->assertDontSee('Prive');

        $studentUser = User::factory()->student()->create();
        $myStudent->forceFill(['user_id' => $studentUser->id])->save();
        $this->actingAs($studentUser)
            ->get(route('student.grades.index'))
            ->assertOk()
            ->assertSee('Test')
            ->assertSee('15,00')
            ->assertDontSee('12,00');
    }

    public function test_evaluation_titles_and_grade_comments_are_escaped_in_html(): void
    {
        $setup = $this->gradeSetup(1);
        $admin = User::factory()->admin()->create();
        $maliciousTitle = '<script>alert(1)</script>';
        $evaluation = app(GradebookService::class)->createEvaluation($setup['assignment'], [
            'title' => $maliciousTitle,
            'type' => 'quiz',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
        ], $admin);
        app(GradebookService::class)->recordGrades($evaluation, [
            $setup['enrollments'][0]->id => [
                'score' => 15,
                'comment' => '<img src=x onerror=alert(1)>',
            ],
        ], $admin);

        $this->actingAs($admin)
            ->get(route('grades.show', $evaluation))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee($maliciousTitle, false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_teaching_assignment_coefficient_cannot_change_after_evaluations_exist(): void
    {
        $setup = $this->gradeSetup(0);
        $admin = User::factory()->admin()->create();
        app(GradebookService::class)->createEvaluation($setup['assignment'], [
            'title' => 'Test',
            'type' => 'quiz',
            'evaluation_date' => now()->toDateString(),
            'coefficient' => 1,
        ], $admin);

        $this->actingAs($admin)
            ->put(route('admin.assignments.update', $setup['assignment']), [
                'subject_id' => $setup['assignment']->subject_id,
                'teacher_id' => $setup['teacher']->id,
                'coefficient' => 3,
                'weekly_hours' => 3,
            ])
            ->assertSessionHas('error');

        $this->assertSame(2, $setup['assignment']->fresh()->coefficient);
    }

    /** @return array{year: SchoolYear, classroom: Classroom, teacherUser: User, teacher: Teacher, assignment: TeachingAssignment, enrollments: array<int, Enrollment>} */
    private function gradeSetup(int $studentCount = 2, ?SchoolYear $year = null): array
    {
        $year ??= SchoolYear::factory()->active()->create([
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(8)->toDateString(),
        ]);
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        $teacherUser = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create();
        $teacher->forceFill(['user_id' => $teacherUser->id])->save();
        $assignment = $classroom->assignments()->create([
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
        ]);
        $enrollments = [];

        for ($index = 0; $index < $studentCount; $index++) {
            $enrollments[] = Enrollment::factory()->create([
                'classroom_id' => $classroom->id,
                'school_year_id' => $year->id,
                'enrolled_at' => now()->subDays(3)->toDateString(),
            ]);
        }

        return compact('year', 'classroom', 'teacherUser', 'teacher', 'assignment', 'enrollments');
    }
}
