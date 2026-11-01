<?php

namespace Tests\Feature\Academics;

use App\Models\Classroom;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Règle d'or : un enseignant ne voit QUE ses classes et ses affectations.
 */
class ClassroomPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function teacherUser(): array
    {
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        return [$user, $teacher];
    }

    public function test_teacher_sees_only_classrooms_where_they_teach(): void
    {
        [$user, $teacher] = $this->teacherUser();
        $mine = Classroom::factory()->create();
        $other = Classroom::factory()->create();
        TeachingAssignment::factory()->create(['classroom_id' => $mine->id, 'teacher_id' => $teacher->id]);

        $this->assertTrue($user->can('view', $mine));
        $this->assertFalse($user->can('view', $other));
        $this->assertFalse($user->can('update', $mine));
        $this->assertFalse($user->can('delete', $mine));
    }

    public function test_head_teacher_sees_their_classroom(): void
    {
        [$user, $teacher] = $this->teacherUser();
        $classroom = Classroom::factory()->create(['head_teacher_id' => $teacher->id]);

        $this->assertTrue($user->can('view', $classroom));
    }

    public function test_teacher_sees_only_their_own_assignments(): void
    {
        [$user, $teacher] = $this->teacherUser();
        $mine = TeachingAssignment::factory()->create(['teacher_id' => $teacher->id]);
        $other = TeachingAssignment::factory()->create();

        $this->assertTrue($user->can('view', $mine));
        $this->assertFalse($user->can('view', $other));
        $this->assertFalse($user->can('update', $mine));
    }

    public function test_teacher_without_profile_sees_nothing(): void
    {
        $user = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();

        $this->assertFalse($user->can('view', $classroom));
    }

    public function test_guardian_and_student_never_see_classrooms(): void
    {
        $classroom = Classroom::factory()->create();

        $this->assertFalse(User::factory()->guardian()->create()->can('view', $classroom));
        $this->assertFalse(User::factory()->student()->create()->can('view', $classroom));
        $this->assertTrue(User::factory()->admin()->create()->can('view', $classroom));
    }

    public function test_teacher_profile_visible_to_admin_and_owner_only(): void
    {
        [$user, $teacher] = $this->teacherUser();
        [$stranger] = $this->teacherUser();

        $this->assertTrue($user->can('view', $teacher));
        $this->assertFalse($stranger->can('view', $teacher));
        $this->assertFalse($user->can('update', $teacher));
        $this->assertTrue(User::factory()->admin()->create()->can('view', $teacher));
    }
}
