<?php

namespace Tests\Feature\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function enrollPayload(Classroom $classroom, array $overrides = []): array
    {
        return array_merge(['classroom_id' => $classroom->id, 'date' => now()->toDateString()], $overrides);
    }

    public function test_admin_enrolls_a_student(): void
    {
        $student = Student::factory()->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.students.enrollments.store', $student), $this->enrollPayload($classroom))
            ->assertRedirect(route('admin.students.show', $student));

        $enrollment = Enrollment::firstOrFail();
        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame($classroom->school_year_id, $enrollment->school_year_id);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertNull($enrollment->ended_at);
    }

    public function test_cannot_enroll_in_a_closed_classroom(): void
    {
        $student = Student::factory()->create();
        $classroom = Classroom::factory()->create(['status' => 'closed']);

        $this->actingAs($this->admin())
            ->post(route('admin.students.enrollments.store', $student), $this->enrollPayload($classroom))
            ->assertSessionHasErrors('classroom_id');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_cannot_enroll_a_suspended_student(): void
    {
        $student = Student::factory()->create(['status' => 'suspended']);
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.students.enrollments.store', $student), $this->enrollPayload($classroom))
            ->assertSessionHasErrors('classroom_id');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_one_active_enrollment_per_school_year(): void
    {
        $year = SchoolYear::factory()->create();
        $classA = Classroom::factory()->create(['school_year_id' => $year->id]);
        $classB = Classroom::factory()->create(['school_year_id' => $year->id]);
        $student = Student::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.students.enrollments.store', $student), $this->enrollPayload($classA));
        $this->actingAs($admin)
            ->post(route('admin.students.enrollments.store', $student), $this->enrollPayload($classB))
            ->assertSessionHasErrors('classroom_id');

        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_capacity_is_enforced(): void
    {
        $classroom = Classroom::factory()->create(['capacity' => 1]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.students.enrollments.store', Student::factory()->create()), $this->enrollPayload($classroom));
        $this->actingAs($admin)
            ->post(route('admin.students.enrollments.store', Student::factory()->create()), $this->enrollPayload($classroom))
            ->assertSessionHasErrors('classroom_id');

        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_student_can_be_reenrolled_the_following_year(): void
    {
        $student = Student::factory()->create();
        $classLastYear = Classroom::factory()->create();
        $classNextYear = Classroom::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.students.enrollments.store', $student), $this->enrollPayload($classLastYear));
        $this->actingAs($admin)
            ->post(route('admin.students.enrollments.store', $student), $this->enrollPayload($classNextYear))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Enrollment::where('student_id', $student->id)->count());
    }

    public function test_future_dates_are_rejected(): void
    {
        $student = Student::factory()->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.students.enrollments.store', $student), $this->enrollPayload($classroom, ['date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('date');
    }

    public function test_transfer_keeps_history_and_stays_in_the_same_year(): void
    {
        $year = SchoolYear::factory()->create();
        $classA = Classroom::factory()->create(['school_year_id' => $year->id]);
        $classB = Classroom::factory()->create(['school_year_id' => $year->id]);
        $old = Enrollment::factory()->create(['classroom_id' => $classA->id, 'enrolled_at' => now()->subDays(10)->toDateString()]);

        $this->actingAs($this->admin())
            ->post(route('admin.enrollments.transfer.store', $old), $this->enrollPayload($classB))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.students.show', $old->student_id));

        $old->refresh();
        $this->assertSame(EnrollmentStatus::Transferred, $old->status);
        $this->assertSame(now()->toDateString(), $old->ended_at->toDateString());

        $current = Enrollment::active()->where('student_id', $old->student_id)->firstOrFail();
        $this->assertSame($classB->id, $current->classroom_id);
        $this->assertSame($year->id, $current->school_year_id);
        $this->assertSame(2, Enrollment::where('student_id', $old->student_id)->count());
    }

    public function test_transfer_to_another_year_or_same_class_is_rejected(): void
    {
        $old = Enrollment::factory()->create();
        $otherYearClass = Classroom::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.enrollments.transfer.store', $old), $this->enrollPayload($otherYearClass))
            ->assertSessionHasErrors('classroom_id');

        $this->actingAs($admin)
            ->post(route('admin.enrollments.transfer.store', $old), $this->enrollPayload($old->classroom))
            ->assertSessionHasErrors('classroom_id');

        $this->assertSame(EnrollmentStatus::Active, $old->fresh()->status);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_transfer_to_a_full_classroom_is_rejected_and_nothing_changes(): void
    {
        $year = SchoolYear::factory()->create();
        $classA = Classroom::factory()->create(['school_year_id' => $year->id]);
        $full = Classroom::factory()->create(['school_year_id' => $year->id, 'capacity' => 1]);
        Enrollment::factory()->create(['classroom_id' => $full->id]);
        $mine = Enrollment::factory()->create(['classroom_id' => $classA->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.enrollments.transfer.store', $mine), $this->enrollPayload($full))
            ->assertSessionHasErrors('classroom_id');

        $this->assertSame(EnrollmentStatus::Active, $mine->fresh()->status);
        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_end_date_cannot_precede_enrollment_date(): void
    {
        $year = SchoolYear::factory()->create();
        $classA = Classroom::factory()->create(['school_year_id' => $year->id]);
        $classB = Classroom::factory()->create(['school_year_id' => $year->id]);
        $enrollment = Enrollment::factory()->create([
            'classroom_id' => $classA->id,
            'enrolled_at' => now()->subDays(2)->toDateString(),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.enrollments.transfer.store', $enrollment), $this->enrollPayload($classB, ['date' => now()->subDays(5)->toDateString()]))
            ->assertSessionHasErrors('classroom_id');

        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_withdrawal_keeps_history_and_allows_a_new_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.enrollments.withdraw', $enrollment), ['date' => now()->toDateString()])
            ->assertRedirect(route('admin.students.show', $enrollment->student_id));

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Withdrawn, $enrollment->status);
        $this->assertNotNull($enrollment->ended_at);

        $this->actingAs($admin)
            ->post(route('admin.students.enrollments.store', $enrollment->student_id), $this->enrollPayload($enrollment->classroom))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Enrollment::where('student_id', $enrollment->student_id)->count());
        $this->assertSame(1, Enrollment::active()->where('student_id', $enrollment->student_id)->count());
    }

    public function test_an_already_ended_enrollment_cannot_be_withdrawn_again(): void
    {
        $enrollment = Enrollment::factory()->withdrawn()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.enrollments.withdraw', $enrollment), ['date' => now()->toDateString()])
            ->assertSessionHas('error');

        $this->actingAs($this->admin())
            ->get(route('admin.enrollments.transfer.create', $enrollment))
            ->assertRedirect(route('admin.students.show', $enrollment->student_id));
    }

    public function test_non_admin_cannot_use_enrollment_features(): void
    {
        $student = Student::factory()->create();
        $classroom = Classroom::factory()->create();
        $enrollment = Enrollment::factory()->create();

        foreach (['teacher', 'guardian', 'student'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get(route('admin.enrollments.index'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('admin.students.enrollments.store', $student), $this->enrollPayload($classroom))
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('admin.enrollments.withdraw', $enrollment), ['date' => now()->toDateString()])
                ->assertForbidden();
        }

        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
    }

    public function test_student_page_shows_the_enrollment_history(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'Classe Historique']);
        $enrollment = Enrollment::factory()->create(['classroom_id' => $classroom->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.students.show', $enrollment->student_id))
            ->assertOk()
            ->assertSee('Classe Historique')
            ->assertSee('En cours');
    }

    public function test_classroom_page_lists_enrolled_students(): void
    {
        $classroom = Classroom::factory()->create();
        $student = Student::factory()->create(['last_name' => 'ZANNOU', 'first_name' => 'Edgar']);
        Enrollment::factory()->create(['classroom_id' => $classroom->id, 'student_id' => $student->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.classrooms.show', $classroom))
            ->assertOk()
            ->assertSee('ZANNOU');
    }

    public function test_index_defaults_to_the_active_year_and_can_search(): void
    {
        $active = SchoolYear::factory()->active()->create();
        $other = SchoolYear::factory()->create();
        $inActive = Enrollment::factory()->create([
            'classroom_id' => Classroom::factory()->create(['school_year_id' => $active->id])->id,
            'student_id' => Student::factory()->create(['last_name' => 'ALPHAZ'])->id,
        ]);
        Enrollment::factory()->create([
            'classroom_id' => Classroom::factory()->create(['school_year_id' => $other->id])->id,
            'student_id' => Student::factory()->create(['last_name' => 'BETAZ'])->id,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.enrollments.index'))
            ->assertOk()->assertSee('ALPHAZ')->assertDontSee('BETAZ');

        $this->actingAs($admin)->get(route('admin.enrollments.index', ['school_year_id' => '']))
            ->assertOk()->assertSee('ALPHAZ')->assertSee('BETAZ');

        $this->actingAs($admin)->get(route('admin.enrollments.index', ['school_year_id' => '', 'search' => 'betaz']))
            ->assertOk()->assertDontSee('ALPHAZ')->assertSee('BETAZ');

        $this->assertNotNull($inActive);
    }

    public function test_enrollment_forms_render(): void
    {
        $enrollment = Enrollment::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.students.enrollments.create', $enrollment->student_id))->assertOk();
        $this->actingAs($admin)->get(route('admin.enrollments.transfer.create', $enrollment))->assertOk();
    }
}
