<?php

namespace Tests\Feature\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolYearManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'label' => '2031-2032',
            'start_date' => '2031-10-01',
            'end_date' => '2032-07-31',
        ], $overrides);
    }

    public function test_admin_creates_an_inactive_school_year(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.school-years.store'), $this->payload())
            ->assertRedirect(route('admin.school-years.index'));

        $year = SchoolYear::where('label', '2031-2032')->firstOrFail();
        $this->assertFalse($year->is_active);
    }

    public function test_label_must_look_like_two_consecutive_years(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.school-years.store'), $this->payload(['label' => '2031/2032']))
            ->assertSessionHasErrors('label');

        $this->actingAs($admin)
            ->post(route('admin.school-years.store'), $this->payload(['label' => '2031-2033']))
            ->assertSessionHasErrors('label');

        $this->assertDatabaseCount('school_years', 0);
    }

    public function test_end_date_must_follow_start_date(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.school-years.store'), $this->payload(['end_date' => '2031-09-01']))
            ->assertSessionHasErrors('end_date');
    }

    public function test_overlapping_periods_are_rejected_but_adjacent_ones_are_fine(): void
    {
        SchoolYear::factory()->create(['label' => '2031-2032', 'start_date' => '2031-10-01', 'end_date' => '2032-07-31']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.school-years.store'), $this->payload([
                'label' => '2032-2033', 'start_date' => '2032-07-15', 'end_date' => '2033-07-31',
            ]))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($admin)
            ->post(route('admin.school-years.store'), $this->payload([
                'label' => '2032-2033', 'start_date' => '2032-10-01', 'end_date' => '2033-07-31',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_editing_a_year_does_not_conflict_with_itself(): void
    {
        $year = SchoolYear::factory()->create(['label' => '2031-2032', 'start_date' => '2031-10-01', 'end_date' => '2032-07-31']);

        $this->actingAs($this->admin())
            ->put(route('admin.school-years.update', $year), $this->payload(['end_date' => '2032-08-15']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.school-years.index'));

        $this->assertSame('2032-08-15', $year->fresh()->end_date->toDateString());
    }

    public function test_school_year_period_cannot_change_after_an_enrollment_or_period_exists(): void
    {
        $year = SchoolYear::factory()->create([
            'label' => '2031-2032',
            'start_date' => '2031-10-01',
            'end_date' => '2032-07-31',
        ]);
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        Enrollment::factory()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $year->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.school-years.update', $year), $this->payload([
                'start_date' => '2031-09-15',
                'end_date' => '2032-07-31',
            ]))
            ->assertSessionHas('error');

        $this->assertSame('2031-10-01', $year->fresh()->start_date->toDateString());
    }

    public function test_school_year_period_cannot_change_after_a_payment_exists(): void
    {
        $year = SchoolYear::factory()->create([
            'label' => '2031-2032',
            'start_date' => '2031-10-01',
            'end_date' => '2032-07-31',
        ]);
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        $enrollment = Enrollment::factory()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $year->id,
        ]);
        Payment::factory()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.school-years.update', $year), $this->payload(['end_date' => '2032-08-15']))
            ->assertSessionHas('error');

        $this->assertSame('2032-07-31', $year->fresh()->end_date->toDateString());
    }

    public function test_activation_switches_the_active_year_and_completes_old_enrollments(): void
    {
        $old = SchoolYear::factory()->active()->create();
        $new = SchoolYear::factory()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $old->id]);
        $enrollment = Enrollment::factory()->create(['classroom_id' => $classroom->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.school-years.activate', $new))
            ->assertRedirect(route('admin.school-years.index'));

        $this->assertTrue($new->fresh()->is_active);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame(1, SchoolYear::active()->count());

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
        $this->assertNotNull($enrollment->ended_at);
        $this->assertTrue($enrollment->ended_at->gte($enrollment->enrolled_at));
    }

    public function test_activation_works_when_no_year_is_active_and_is_idempotent(): void
    {
        $year = SchoolYear::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.school-years.activate', $year));
        $this->actingAs($admin)->post(route('admin.school-years.activate', $year));

        $this->assertTrue($year->fresh()->is_active);
        $this->assertSame(1, SchoolYear::active()->count());
    }

    public function test_active_year_and_year_with_classrooms_cannot_be_deleted(): void
    {
        $active = SchoolYear::factory()->active()->create();
        $withClass = SchoolYear::factory()->create();
        Classroom::factory()->create(['school_year_id' => $withClass->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('admin.school-years.destroy', $active))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.school-years.destroy', $withClass))->assertSessionHas('error');

        $this->assertModelExists($active);
        $this->assertModelExists($withClass);
    }

    public function test_empty_inactive_year_can_be_deleted(): void
    {
        $year = SchoolYear::factory()->create();

        $this->actingAs($this->admin())->delete(route('admin.school-years.destroy', $year));

        $this->assertModelMissing($year);
    }

    public function test_non_admin_cannot_manage_school_years(): void
    {
        $year = SchoolYear::factory()->create();

        foreach (['teacher', 'guardian', 'student'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get(route('admin.school-years.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.school-years.activate', $year))->assertForbidden();
        }

        $this->assertFalse($year->fresh()->is_active);
    }

    public function test_pages_render(): void
    {
        $year = SchoolYear::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.school-years.index'))->assertOk()->assertSee($year->label);
        $this->actingAs($admin)->get(route('admin.school-years.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.school-years.edit', $year))->assertOk();
    }
}
