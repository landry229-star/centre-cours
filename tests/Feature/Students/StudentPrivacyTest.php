<?php

namespace Tests\Feature\Students;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Règle d'or : un parent ne voit JAMAIS les enfants d'un autre parent.
 */
class StudentPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_limits_access_to_own_children(): void
    {
        $parentA = User::factory()->guardian()->create();
        $parentB = User::factory()->guardian()->create();
        $guardianA = Guardian::factory()->create(['user_id' => $parentA->id]);
        Guardian::factory()->create(['user_id' => $parentB->id]);
        $childOfA = Student::factory()->for($guardianA)->create();

        $this->assertTrue($parentA->can('view', $childOfA));
        $this->assertFalse($parentB->can('view', $childOfA));
        $this->assertFalse($parentA->can('update', $childOfA));
        $this->assertTrue(User::factory()->admin()->create()->can('view', $childOfA));
        $this->assertFalse(User::factory()->teacher()->create()->can('view', $childOfA));
    }

    public function test_student_can_only_view_own_record(): void
    {
        $account = User::factory()->student()->create();
        $own = Student::factory()->create(['user_id' => $account->id]);
        $other = Student::factory()->create();

        $this->assertTrue($account->can('view', $own));
        $this->assertFalse($account->can('view', $other));
    }

    public function test_photo_route_is_protected_by_the_policy(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('students/photos/test.jpg', 'fake-image-content');

        $owner = User::factory()->guardian()->create();
        $stranger = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $owner->id]);
        $student = Student::factory()->for($guardian)->create(['photo_path' => 'students/photos/test.jpg']);

        $this->actingAs($owner)->get(route('students.photo', $student))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('students.photo', $student))->assertOk();
        $this->actingAs($stranger)->get(route('students.photo', $student))->assertForbidden();
        $this->actingAs(User::factory()->teacher()->create())->get(route('students.photo', $student))->assertForbidden();
    }

    public function test_photo_route_requires_authentication(): void
    {
        $student = Student::factory()->create(['photo_path' => 'students/photos/test.jpg']);

        $this->get(route('students.photo', $student))->assertRedirect(route('login'));
    }
}
