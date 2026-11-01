<?php

namespace Tests\Feature\Academics;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_assigns_a_subject_to_a_teacher_in_a_classroom(): void
    {
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.classrooms.assignments.store', $classroom), [
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'coefficient' => 4,
                'weekly_hours' => 5,
            ])
            ->assertRedirect(route('admin.classrooms.show', $classroom));

        $this->assertDatabaseHas('teaching_assignments', [
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 4,
        ]);
    }

    public function test_a_subject_has_only_one_teacher_per_classroom(): void
    {
        $assignment = TeachingAssignment::factory()->create();
        $otherTeacher = Teacher::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.classrooms.assignments.store', $assignment->classroom_id), [
                'subject_id' => $assignment->subject_id,
                'teacher_id' => $otherTeacher->id,
                'coefficient' => 2,
            ])
            ->assertSessionHasErrors('subject_id');

        $this->assertDatabaseCount('teaching_assignments', 1);
    }

    public function test_same_subject_can_be_taught_in_another_classroom(): void
    {
        $assignment = TeachingAssignment::factory()->create();
        $otherClassroom = Classroom::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.classrooms.assignments.store', $otherClassroom), [
                'subject_id' => $assignment->subject_id,
                'teacher_id' => $assignment->teacher_id,
                'coefficient' => 3,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('teaching_assignments', 2);
    }

    public function test_inactive_teacher_cannot_receive_a_new_assignment(): void
    {
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create();
        $teacher = Teacher::factory()->inactive()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.classrooms.assignments.store', $classroom), [
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'coefficient' => 2,
            ])
            ->assertSessionHasErrors('teacher_id');
    }

    public function test_coefficient_is_validated(): void
    {
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.classrooms.assignments.store', $classroom), [
                'subject_id' => Subject::factory()->create()->id,
                'teacher_id' => Teacher::factory()->create()->id,
                'coefficient' => 11,
            ])
            ->assertSessionHasErrors('coefficient');
    }

    public function test_assignment_can_be_updated_and_removed(): void
    {
        $assignment = TeachingAssignment::factory()->create(['coefficient' => 2]);
        $newTeacher = Teacher::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.assignments.update', $assignment), [
                'subject_id' => $assignment->subject_id,
                'teacher_id' => $newTeacher->id,
                'coefficient' => 5,
                'weekly_hours' => 4,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.classrooms.show', $assignment->classroom_id));

        $this->assertSame($newTeacher->id, $assignment->fresh()->teacher_id);
        $this->assertSame(5, $assignment->fresh()->coefficient);

        $this->actingAs($admin)->delete(route('admin.assignments.destroy', $assignment));

        $this->assertModelMissing($assignment);
    }

    public function test_non_admin_cannot_assign(): void
    {
        $classroom = Classroom::factory()->create();

        foreach (['teacher', 'guardian', 'student'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->post(route('admin.classrooms.assignments.store', $classroom), [
                    'subject_id' => Subject::factory()->create()->id,
                    'teacher_id' => Teacher::factory()->create()->id,
                    'coefficient' => 2,
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_database_rejects_an_out_of_range_coefficient(): void
    {
        $this->expectException(QueryException::class);

        TeachingAssignment::factory()->create(['coefficient' => 11]);
    }
}
