<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Grades\GradebookService;
use App\Services\Grades\ReportCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_bulletins(): void
    {
        $setup = $this->reportSetup();

        $this->get(route('report-cards.index'))->assertRedirect(route('login'));
        $this->get(route('report-cards.show', $setup['enrollment']))->assertRedirect(route('login'));
    }

    public function test_teacher_cannot_open_bulletins(): void
    {
        $setup = $this->reportSetup();
        $teacherUser = User::factory()->teacher()->create();

        $this->actingAs($teacherUser)
            ->get(route('report-cards.index'))
            ->assertForbidden();

        $this->actingAs($teacherUser)
            ->get(route('report-cards.show', $setup['enrollment']))
            ->assertForbidden();
    }

    public function test_guardian_can_view_bulletin_choices_and_download_their_childs_report(): void
    {
        $setup = $this->reportSetup();
        $guardianUser = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
        $setup['student']->forceFill(['guardian_id' => $guardian->id])->save();
        app(GradebookService::class)->recordGrades($setup['evaluation'], [
            $setup['enrollment']->id => ['score' => 17],
        ], User::factory()->admin()->create());
        app(GradebookService::class)->publishEvaluation($setup['evaluation'], User::factory()->admin()->create());

        $this->actingAs($guardianUser)
            ->get(route('report-cards.index'))
            ->assertOk()
            ->assertSeeText($setup['student']->full_name)
            ->assertSee('Choisir une période');

        $this->actingAs($guardianUser)
            ->get(route('report-cards.show', $setup['enrollment']))
            ->assertOk()
            ->assertSee('Trimestre 1')
            ->assertSee('Semestre 1')
            ->assertSee('Année complète');

        $response = $this->actingAs($guardianUser)
            ->get(route('report-cards.show', [
                'enrollment' => $setup['enrollment'],
                'period_type' => 'trimester',
                'period_number' => 1,
            ]))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_guardian_cannot_view_another_familys_bulletin(): void
    {
        $setup = $this->reportSetup();
        $ownStudent = Student::factory()->create();
        $ownEnrollment = Enrollment::factory()->create([
            'student_id' => $ownStudent->id,
            'classroom_id' => $setup['classroom']->id,
            'school_year_id' => $setup['schoolYear']->id,
            'enrolled_at' => '2030-08-01',
        ]);
        app(GradebookService::class)->recordGrades($setup['evaluation'], [
            $ownEnrollment->id => ['score' => 13],
        ], User::factory()->admin()->create());
        app(GradebookService::class)->publishEvaluation($setup['evaluation'], User::factory()->admin()->create());
        $otherGuardianUser = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $otherGuardianUser->id]);
        $ownStudent->forceFill(['guardian_id' => $guardian->id])->save();

        $this->actingAs($otherGuardianUser)
            ->get(route('report-cards.index'))
            ->assertSeeText($ownStudent->full_name)
            ->assertDontSeeText($setup['student']->full_name);

        $this->actingAs($otherGuardianUser)
            ->get(route('report-cards.show', $setup['enrollment']))
            ->assertForbidden();
    }

    public function test_student_can_download_an_annual_bulletin_for_their_own_enrollment(): void
    {
        $setup = $this->reportSetup();
        $studentUser = User::factory()->student()->create();
        $setup['student']->forceFill(['user_id' => $studentUser->id])->save();
        app(GradebookService::class)->recordGrades($setup['evaluation'], [
            $setup['enrollment']->id => ['score' => 15],
        ], User::factory()->admin()->create());
        app(GradebookService::class)->publishEvaluation($setup['evaluation'], User::factory()->admin()->create());

        $response = $this->actingAs($studentUser)
            ->get(route('report-cards.show', [
                'enrollment' => $setup['enrollment'],
                'period_type' => 'annual',
                'period_number' => 1,
                'download' => 1,
            ]))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_admin_can_view_all_bulletins_and_download_a_semester_report(): void
    {
        $setup = $this->reportSetup();
        $admin = User::factory()->admin()->create();
        app(GradebookService::class)->recordGrades($setup['evaluation'], [
            $setup['enrollment']->id => ['score' => 12],
        ], $admin);

        $this->actingAs($admin)
            ->get(route('report-cards.index'))
            ->assertOk()
            ->assertSeeText($setup['student']->full_name);

        $response = $this->actingAs($admin)
            ->get(route('report-cards.show', [
                'enrollment' => $setup['enrollment'],
                'period_type' => 'semester',
                'period_number' => 2,
            ]))
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invalid_period_number_is_rejected(): void
    {
        $setup = $this->reportSetup();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('report-cards.show', [
                'enrollment' => $setup['enrollment'],
                'period_type' => 'semester',
                'period_number' => 3,
            ]))
            ->assertSessionHasErrors('period_number');
    }

    public function test_report_averages_and_rankings_only_include_grades_in_the_selected_period(): void
    {
        $setup = $this->reportSetup();
        $secondStudent = Student::factory()->create();
        $secondEnrollment = Enrollment::factory()->create([
            'student_id' => $secondStudent->id,
            'classroom_id' => $setup['classroom']->id,
            'school_year_id' => $setup['schoolYear']->id,
            'enrolled_at' => '2030-08-01',
        ]);
        $admin = User::factory()->admin()->create();
        $gradebook = app(GradebookService::class);
        $gradebook->recordGrades($setup['evaluation'], [
            $setup['enrollment']->id => ['score' => 18],
            $secondEnrollment->id => ['score' => 10],
        ], $admin);
        $laterEvaluation = $gradebook->createEvaluation($setup['assignment'], [
            'title' => 'Deuxième période',
            'type' => 'quiz',
            'evaluation_date' => '2031-01-15',
            'coefficient' => 1,
        ], $admin);
        $gradebook->recordGrades($laterEvaluation, [
            $setup['enrollment']->id => ['score' => 12],
        ], $admin);
        $gradebook->publishEvaluation($setup['evaluation'], $admin);
        $gradebook->publishEvaluation($laterEvaluation, $admin);

        $report = app(ReportCardService::class)->make($setup['enrollment'], 'trimester', 1);
        $annualReport = app(ReportCardService::class)->make($setup['enrollment'], 'annual', 1);

        expect($report['average'])->toBe(18.0);
        expect($report['rank'])->toBe(1);
        expect($annualReport['average'])->toBe(15.0);
    }

    public function test_bulletin_without_recorded_scores_shows_no_average_or_rank(): void
    {
        $setup = $this->reportSetup();

        $report = app(ReportCardService::class)->make($setup['enrollment'], 'annual', 1);

        expect($report['average'])->toBeNull();
        expect($report['rank'])->toBeNull();
        expect($report['subjects'])->toBeEmpty();
    }

    private function reportSetup(): array
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
        $teacher = Teacher::factory()->create();
        $assignment = TeachingAssignment::factory()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $teacher->id,
        ]);
        $admin = User::factory()->admin()->create();
        $evaluation = app(GradebookService::class)->createEvaluation($assignment, [
            'title' => 'Évaluation initiale',
            'type' => 'quiz',
            'evaluation_date' => '2030-10-15',
            'coefficient' => 1,
        ], $admin);

        return compact('schoolYear', 'classroom', 'student', 'enrollment', 'assignment', 'evaluation');
    }
}
