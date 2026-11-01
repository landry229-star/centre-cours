<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_each_role_is_redirected_to_its_own_dashboard(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get('/dashboard')
                ->assertRedirect(route($role->dashboardRoute()));
        }
    }

    public function test_each_role_can_open_its_own_dashboard(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route($role->dashboardRoute()))
                ->assertOk();
        }
    }

    public function test_admin_dashboard_shows_school_metrics_and_administration_navigation(): void
    {
        $year = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        Enrollment::factory()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $year->id,
        ]);
        Teacher::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Élèves actifs')
            ->assertSee('Inscriptions en cours')
            ->assertSee($year->label)
            ->assertSee(route('admin.students.index'))
            ->assertSee('Établissement');
    }

    public function test_teacher_dashboard_only_shows_their_active_assignments(): void
    {
        $year = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        $teacherUser = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create();
        $teacher->forceFill(['user_id' => $teacherUser->id])->save();
        $subject = Subject::factory()->create(['name' => 'Mathématiques']);
        TeachingAssignment::factory()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
        $unrelatedClassroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        TeachingAssignment::factory()->create([
            'classroom_id' => $unrelatedClassroom->id,
            'subject_id' => Subject::factory()->create(['name' => 'Matière privée']),
            'teacher_id' => Teacher::factory()->create()->id,
        ]);
        Enrollment::factory()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $year->id,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Mes classes et matières')
            ->assertSee('Mathématiques')
            ->assertSee($classroom->name)
            ->assertSee(route('attendance.create'))
            ->assertDontSee('Matière privée')
            ->assertDontSee($unrelatedClassroom->name)
            ->assertDontSee(route('admin.students.index'));
    }

    public function test_guardian_dashboard_lists_only_children_linked_to_the_guardian(): void
    {
        $year = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        $guardianUser = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create();
        $guardian->forceFill(['user_id' => $guardianUser->id])->save();
        $child = Student::factory()->create();
        $child->forceFill([
            'guardian_id' => $guardian->id,
            'first_name' => 'EnfantVisible',
        ])->save();
        Enrollment::factory()->create([
            'student_id' => $child->id,
            'classroom_id' => $classroom->id,
            'school_year_id' => $year->id,
        ]);
        $unrelatedChild = Student::factory()->create();
        $unrelatedChild->forceFill(['first_name' => 'EnfantPrive'])->save();
        Enrollment::factory()->create([
            'student_id' => $unrelatedChild->id,
            'classroom_id' => $classroom->id,
            'school_year_id' => $year->id,
        ]);

        $this->actingAs($guardianUser)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('EnfantVisible')
            ->assertDontSee('EnfantPrive')
            ->assertSee(route('guardian.attendance.index'))
            ->assertDontSee(route('admin.students.index'));
    }

    public function test_student_dashboard_shows_the_active_class_and_handles_an_empty_gradebook(): void
    {
        $year = SchoolYear::factory()->active()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        $studentUser = User::factory()->student()->create();
        $student = Student::factory()->create();
        $student->forceFill(['user_id' => $studentUser->id])->save();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'school_year_id' => $year->id,
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee($classroom->name)
            ->assertSee('Pas encore de note')
            ->assertSee(route('student.grades.index'))
            ->assertDontSee(route('admin.students.index'));
    }

    public function test_a_role_cannot_open_another_roles_dashboard(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach (Role::cases() as $other) {
                if ($other === $role) {
                    continue;
                }

                $this->actingAs($user)
                    ->get(route($other->dashboardRoute()))
                    ->assertForbidden();
            }
        }
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_user_deactivated_during_session_is_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_access_authenticated_auth_routes(): void
    {
        $user = User::factory()->unverified()->inactive()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );
        $requests = [
            fn () => $this->get(route('verification.notice')),
            fn () => $this->get($verificationUrl),
            fn () => $this->post(route('verification.send')),
            fn () => $this->get(route('password.confirm')),
            fn () => $this->post(route('password.confirm'), ['password' => 'password']),
            fn () => $this->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Nouveau-mdp-2026',
                'password_confirmation' => 'Nouveau-mdp-2026',
            ]),
        ];

        foreach ($requests as $request) {
            $this->actingAs($user);

            $request()->assertRedirect(route('login'));

            $this->assertGuest();
        }
    }

    public function test_inactive_user_can_still_log_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Pirate',
            'email' => 'pirate@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(404);
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $user = $user->fresh();

        $this->assertSame(Role::Guardian, $user->role);
        $this->assertTrue($user->is_active);
    }
}
