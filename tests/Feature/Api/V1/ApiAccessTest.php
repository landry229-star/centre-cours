<?php

namespace Tests\Feature\Api\V1;

use App\Events\PaymentRecorded;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesBilling;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use CreatesBilling, RefreshDatabase;

    public function test_active_user_can_login_read_their_profile_and_revoke_the_token(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'telephone-test',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.token_type', 'Bearer');
        $token = $response->json('data.access_token');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_inactive_user_cannot_obtain_an_api_token(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_deactivated_account_is_blocked_from_api_data_but_can_revoke_its_token(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->tokenFor($user);
        $user->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();

        Auth::forgetGuards();
        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_temporary_password_user_can_only_update_password_before_using_the_api(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();
        $token = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.password_change_required', true)
            ->json('data.access_token');

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
        $this->withToken($token)->putJson('/api/v1/password', [
            'current_password' => 'password',
            'password' => 'New-strong-password-2031',
            'password_confirmation' => 'New-strong-password-2031',
        ])->assertOk();

        $this->assertFalse($user->fresh()->must_change_password);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    public function test_guardian_only_reads_their_students_and_cannot_probe_another_family(): void
    {
        [$guardianUser, $guardian] = $this->guardianAccount();
        [, $otherGuardian] = $this->guardianAccount();
        $child = Student::factory()->create([
            'guardian_id' => $guardian->id,
            'first_name' => 'EnfantVisibleAPI',
        ]);
        $privateChild = Student::factory()->create([
            'guardian_id' => $otherGuardian->id,
            'first_name' => 'EnfantPriveAPI',
        ]);

        $this->actingAs($guardianUser)->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $child->id)
            ->assertJsonPath('data.0.first_name', 'EnfantVisibleAPI');

        $this->actingAs($guardianUser)->getJson('/api/v1/students/'.$privateChild->id)->assertNotFound();
    }

    public function test_student_can_read_only_their_own_student_record(): void
    {
        $user = User::factory()->student()->create();
        $student = Student::factory()->create(['first_name' => 'MonProfilAPI']);
        $student->forceFill(['user_id' => $user->id])->save();
        $otherStudent = Student::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $student->id)
            ->assertJsonPath('data.0.birth_date', $student->birth_date->toDateString());

        $this->actingAs($user)->getJson('/api/v1/students/'.$otherStudent->id)->assertNotFound();
    }

    public function test_teacher_can_record_attendance_only_for_an_assigned_session(): void
    {
        $setup = $this->teacherClassSetup();
        $session = AttendanceSession::factory()->forAssignment($setup['assignment'])->create([
            'session_date' => now()->toDateString(),
        ]);
        $enrollment = $setup['enrollment'];

        $this->withToken($this->tokenFor($setup['user']))
            ->postJson('/api/v1/attendance/sessions/'.$session->id.'/records', [
                'entries' => [$enrollment->id => ['status' => 'present']],
            ])
            ->assertOk()
            ->assertJsonPath('data.summary.present', 1);

        $this->assertDatabaseHas('attendances', [
            'attendance_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'present',
            'marked_by' => $setup['user']->id,
        ]);

        $this->withToken($this->tokenFor($setup['user']))
            ->postJson('/api/v1/attendance/sessions/'.$session->id.'/records', [
                'entries' => [$enrollment->id => ['status' => 'unknown']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entries.'.$enrollment->id.'.status');

        $otherTeacher = User::factory()->teacher()->create();
        Auth::forgetGuards();
        $this->withToken($this->tokenFor($otherTeacher))
            ->postJson('/api/v1/attendance/sessions/'.$session->id.'/records', [
                'entries' => [$enrollment->id => ['status' => 'absent']],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('attendances', ['status' => 'present', 'enrollment_id' => $enrollment->id]);
    }

    public function test_admin_reads_teacher_profiles_and_teacher_reads_only_assigned_attendance_sessions(): void
    {
        $setup = $this->teacherClassSetup();
        $session = AttendanceSession::factory()->forAssignment($setup['assignment'])->create();
        AttendanceSession::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/v1/teachers')
            ->assertOk()
            ->assertJsonFragment(['id' => $setup['assignment']->teacher_id]);

        Auth::forgetGuards();
        $this->withToken($this->tokenFor($setup['user']))->getJson('/api/v1/attendance/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $session->id)
            ->assertJsonPath('data.0.classroom.id', $setup['classroom']->id);
    }

    public function test_assigned_teacher_can_record_grades_but_another_teacher_cannot(): void
    {
        $setup = $this->teacherClassSetup();
        $evaluation = Evaluation::factory()->forAssignment($setup['assignment'])->create();

        $this->withToken($this->tokenFor($setup['user']))
            ->postJson('/api/v1/evaluations/'.$evaluation->id.'/grades', [
                'entries' => [$setup['enrollment']->id => ['score' => 16, 'comment' => 'Bon travail']],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Notes enregistrées.');

        $this->assertDatabaseHas('grades', [
            'evaluation_id' => $evaluation->id,
            'enrollment_id' => $setup['enrollment']->id,
            'score' => 16,
            'entered_by' => $setup['user']->id,
        ]);

        $otherTeacher = User::factory()->teacher()->create();
        Auth::forgetGuards();
        $this->withToken($this->tokenFor($otherTeacher))
            ->postJson('/api/v1/evaluations/'.$evaluation->id.'/grades', [
                'entries' => [$setup['enrollment']->id => ['score' => 12]],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('grades', 1);
    }

    public function test_guardian_only_sees_published_grades_for_their_child(): void
    {
        [$guardianUser, $guardian] = $this->guardianAccount();
        $setup = $this->teacherClassSetup();
        $child = Student::factory()->create(['guardian_id' => $guardian->id]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $child->id,
            'classroom_id' => $setup['classroom']->id,
            'school_year_id' => $setup['year']->id,
            'enrolled_at' => now()->toDateString(),
        ]);
        $admin = User::factory()->admin()->create();
        $published = Evaluation::factory()->forAssignment($setup['assignment'])->create();
        $published->forceFill(['published_at' => now(), 'published_by' => $admin->id])->save();
        $unpublished = Evaluation::factory()->forAssignment($setup['assignment'])->create();
        $publishedGrade = Grade::factory()->forEvaluation($published, $enrollment)->create(['score' => 17]);
        Grade::factory()->forEvaluation($unpublished, $enrollment)->create(['score' => 8]);

        $this->actingAs($guardianUser)->getJson('/api/v1/grades')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $publishedGrade->id)
            ->assertJsonPath('data.0.score', '17.00');
    }

    public function test_guardian_can_only_read_their_active_class_subjects_and_schedule(): void
    {
        [$guardianUser, $guardian] = $this->guardianAccount();
        $setup = $this->teacherClassSetup();
        $setup['enrollment']->student->forceFill(['guardian_id' => $guardian->id])->save();
        $schedule = Schedule::factory()->forAssignment($setup['assignment'])->create();

        $this->actingAs($guardianUser)->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $setup['classroom']->id);

        $this->actingAs($guardianUser)->getJson('/api/v1/subjects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $setup['assignment']->subject_id);

        $this->actingAs($guardianUser)->getJson('/api/v1/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $schedule->id);
    }

    public function test_only_admin_can_create_a_payment_and_parent_reads_only_their_receipts(): void
    {
        $this->goToJanuary2031();
        [$guardianUser, $guardian] = $this->guardianAccount();
        [, $otherGuardian] = $this->guardianAccount();
        $enrollment = $this->billingEnrollment(guardian: $guardian);
        $otherEnrollment = $this->billingEnrollment(guardian: $otherGuardian);
        $payment = $this->pay($enrollment, '2030-10-01', 5000);
        $otherPayment = $this->pay($otherEnrollment, '2030-10-01', 5000);

        $this->actingAs($guardianUser)->getJson('/api/v1/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payment->id);

        $this->actingAs($guardianUser)->getJson('/api/v1/payments/'.$otherPayment->id)->assertNotFound();
        $this->actingAs(User::factory()->teacher()->create())
            ->getJson('/api/v1/payments')
            ->assertForbidden();
    }

    public function test_admin_records_payment_through_the_billing_service(): void
    {
        $this->goToJanuary2031();
        $enrollment = $this->billingEnrollment();
        $admin = User::factory()->admin()->create();
        Event::fake([PaymentRecorded::class]);

        $response = $this->actingAs($admin)->postJson('/api/v1/payments', [
            'enrollment_id' => $enrollment->id,
            'month' => '2030-10-01',
            'amount' => 15000,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.amount', 15000)
            ->assertJsonPath('data.method', 'cash');

        $this->assertDatabaseHas('payments', [
            'id' => $response->json('data.id'),
            'enrollment_id' => $enrollment->id,
            'amount' => 15000,
            'recorded_by' => $admin->id,
        ]);
        Event::assertDispatched(PaymentRecorded::class);
    }

    public function test_api_rejects_an_invalid_payment_method_without_persisting_a_payment(): void
    {
        $this->goToJanuary2031();
        $enrollment = $this->billingEnrollment();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/payments', [
            'enrollment_id' => $enrollment->id,
            'month' => '2030-10-01',
            'amount' => 1000,
            'method' => 'bitcoin',
            'paid_at' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('method');

        $this->assertDatabaseCount('payments', 0);
    }

    private function guardianAccount(): array
    {
        $user = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create();
        $guardian->forceFill(['user_id' => $user->id])->save();

        return [$user, $guardian];
    }

    /** @return array{user: User, year: SchoolYear, classroom: Classroom, assignment: TeachingAssignment, enrollment: Enrollment} */
    private function teacherClassSetup(): array
    {
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create();
        $teacher->forceFill(['user_id' => $user->id])->save();
        $year = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        $assignment = $classroom->assignments()->create([
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 1,
        ]);
        $enrollment = Enrollment::factory()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $year->id,
            'enrolled_at' => now()->toDateString(),
        ]);

        return compact('user', 'year', 'classroom', 'assignment', 'enrollment');
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('api-test')->plainTextToken;
    }
}
