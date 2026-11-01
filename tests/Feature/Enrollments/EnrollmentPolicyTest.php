<?php

namespace Tests\Feature\Enrollments;

use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Règle d'or : un parent ne voit JAMAIS la scolarité des enfants d'un autre parent.
 */
class EnrollmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_sees_only_the_enrollments_of_their_own_children(): void
    {
        $owner = User::factory()->guardian()->create();
        $stranger = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $owner->id]);
        Guardian::factory()->create(['user_id' => $stranger->id]);
        $student = Student::factory()->for($guardian)->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);

        $this->assertTrue($owner->can('view', $enrollment));
        $this->assertFalse($stranger->can('view', $enrollment));
        $this->assertFalse($owner->can('update', $enrollment));
    }

    public function test_student_sees_only_their_own_enrollments(): void
    {
        $account = User::factory()->student()->create();
        $own = Enrollment::factory()->create([
            'student_id' => Student::factory()->create(['user_id' => $account->id])->id,
        ]);
        $other = Enrollment::factory()->create();

        $this->assertTrue($account->can('view', $own));
        $this->assertFalse($account->can('view', $other));
    }

    public function test_teacher_has_no_access_and_nobody_can_delete(): void
    {
        $enrollment = Enrollment::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertFalse(User::factory()->teacher()->create()->can('view', $enrollment));
        $this->assertTrue($admin->can('view', $enrollment));
        $this->assertTrue($admin->can('update', $enrollment));
        $this->assertFalse($admin->can('delete', $enrollment));
    }
}
