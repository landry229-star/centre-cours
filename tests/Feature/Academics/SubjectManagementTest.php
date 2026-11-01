<?php

namespace Tests\Feature\Academics;

use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_creates_a_subject_and_code_is_uppercased(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.subjects.store'), ['name' => 'Mathématiques', 'code' => 'math'])
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseHas('subjects', ['name' => 'Mathématiques', 'code' => 'MATH']);
    }

    public function test_name_and_code_must_be_unique(): void
    {
        Subject::factory()->create(['name' => 'Français', 'code' => 'FRA']);

        $this->actingAs($this->admin())
            ->post(route('admin.subjects.store'), ['name' => 'Français', 'code' => 'XXX'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin())
            ->post(route('admin.subjects.store'), ['name' => 'Autre', 'code' => 'fra'])
            ->assertSessionHasErrors('code');
    }

    public function test_updating_keeps_its_own_unique_values(): void
    {
        $subject = Subject::factory()->create(['name' => 'Anglais', 'code' => 'ANG']);

        $this->actingAs($this->admin())
            ->put(route('admin.subjects.update', $subject), ['name' => 'Anglais', 'code' => 'ANG'])
            ->assertRedirect(route('admin.subjects.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_subject_taught_somewhere_cannot_be_deleted(): void
    {
        $assignment = TeachingAssignment::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.subjects.destroy', $assignment->subject))
            ->assertSessionHas('error');

        $this->assertModelExists($assignment->subject);
    }

    public function test_unused_subject_can_be_deleted(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.subjects.destroy', $subject));

        $this->assertModelMissing($subject);
    }

    public function test_non_admin_cannot_manage_subjects(): void
    {
        foreach (['teacher', 'guardian', 'student'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('admin.subjects.index'))
                ->assertForbidden();
        }
    }
}
