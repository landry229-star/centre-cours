<?php

namespace Tests\Feature\Academics;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Academics\ClassroomService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(SchoolYear $year, Level $level, array $overrides = []): array
    {
        return array_merge([
            'school_year_id' => $year->id,
            'level_id' => $level->id,
            'name' => '6ème A',
            'capacity' => 35,
            'room_id' => '',
            'head_teacher_id' => '',
            'status' => 'active',
        ], $overrides);
    }

    public function test_admin_creates_a_classroom(): void
    {
        $year = SchoolYear::factory()->create();
        $level = Level::factory()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.classrooms.store'), $this->payload($year, $level, ['head_teacher_id' => $teacher->id]))
            ->assertRedirect();

        $classroom = Classroom::firstOrFail();
        $this->assertSame('6ème A', $classroom->name);
        $this->assertSame($teacher->id, $classroom->head_teacher_id);
        $this->assertNull($classroom->room_id);
    }

    public function test_name_must_be_unique_within_a_school_year_only(): void
    {
        $year = SchoolYear::factory()->create();
        $otherYear = SchoolYear::factory()->create();
        $level = Level::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.classrooms.store'), $this->payload($year, $level));

        $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->payload($year, $level))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->payload($otherYear, $level))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('classrooms', 2);
    }

    public function test_an_archived_classroom_name_can_be_reused(): void
    {
        $year = SchoolYear::factory()->create();
        $level = Level::factory()->create();
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id, 'level_id' => $level->id, 'name' => '5ème B']);

        app(ClassroomService::class)->delete($classroom);

        $this->actingAs($this->admin())
            ->post(route('admin.classrooms.store'), $this->payload($year, $level, ['name' => '5ème B']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('classrooms', 2);
    }

    public function test_classroom_level_cannot_change_after_a_payment_exists(): void
    {
        $classroom = Classroom::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $classroom->school_year_id,
        ]);
        Payment::factory()->create(['enrollment_id' => $enrollment->id]);
        $newLevel = Level::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.classrooms.update', $classroom), $this->payload(
                $classroom->schoolYear,
                $newLevel,
                ['name' => $classroom->name],
            ))
            ->assertSessionHas('error');

        $this->assertSame($classroom->level_id, $classroom->fresh()->level_id);
    }

    public function test_classroom_level_cannot_change_after_an_enrollment_exists_without_payments(): void
    {
        $classroom = Classroom::factory()->create();
        Enrollment::factory()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $classroom->school_year_id,
        ]);
        $newLevel = Level::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.classrooms.update', $classroom), $this->payload(
                $classroom->schoolYear,
                $newLevel,
                ['name' => $classroom->name],
            ))
            ->assertSessionHas('error');

        $this->assertSame($classroom->level_id, $classroom->fresh()->level_id);
    }

    public function test_classroom_level_cannot_change_after_a_historical_enrollment_exists(): void
    {
        $classroom = Classroom::factory()->create();
        Enrollment::factory()->withdrawn()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $classroom->school_year_id,
        ]);
        $newLevel = Level::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.classrooms.update', $classroom), $this->payload(
                $classroom->schoolYear,
                $newLevel,
                ['name' => $classroom->name],
            ))
            ->assertSessionHas('error');

        $this->assertSame($classroom->level_id, $classroom->fresh()->level_id);
    }

    public function test_classroom_level_can_change_before_any_payment_exists(): void
    {
        $classroom = Classroom::factory()->create();
        $newLevel = Level::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.classrooms.update', $classroom), $this->payload(
                $classroom->schoolYear,
                $newLevel,
                ['name' => $classroom->name],
            ))
            ->assertRedirect(route('admin.classrooms.show', $classroom));

        $this->assertSame($newLevel->id, $classroom->fresh()->level_id);
    }

    public function test_classroom_school_year_cannot_change_after_an_enrollment_exists(): void
    {
        $classroom = Classroom::factory()->create();
        Enrollment::factory()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $classroom->school_year_id,
        ]);
        $otherYear = SchoolYear::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.classrooms.update', $classroom), $this->payload(
                $otherYear,
                $classroom->level,
                ['name' => $classroom->name, 'school_year_id' => $otherYear->id],
            ))
            ->assertSessionHas('error');

        $this->assertSame($classroom->school_year_id, $classroom->fresh()->school_year_id);
    }

    public function test_classroom_with_active_enrollments_cannot_be_archived(): void
    {
        $classroom = Classroom::factory()->create();
        Enrollment::factory()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $classroom->school_year_id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.classrooms.destroy', $classroom))
            ->assertSessionHas('error', "Impossible d'archiver cette classe : des inscriptions sont encore en cours. Clôturez-les d'abord.");

        $this->assertNotSoftDeleted($classroom);
    }

    public function test_classroom_with_only_closed_enrollments_can_be_archived(): void
    {
        $classroom = Classroom::factory()->create();
        Enrollment::factory()->withdrawn()->create([
            'classroom_id' => $classroom->id,
            'school_year_id' => $classroom->school_year_id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.classrooms.destroy', $classroom))
            ->assertRedirect(route('admin.classrooms.index'));

        $this->assertSoftDeleted($classroom);
    }

    public function test_database_enforces_unique_name_per_year(): void
    {
        $year = SchoolYear::factory()->create();
        Classroom::factory()->create(['school_year_id' => $year->id, 'name' => 'Doublon']);

        $this->expectException(QueryException::class);

        Classroom::factory()->create(['school_year_id' => $year->id, 'name' => 'Doublon']);
    }

    public function test_head_teacher_must_exist_and_capacity_must_be_positive(): void
    {
        $year = SchoolYear::factory()->create();
        $level = Level::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.classrooms.store'), $this->payload($year, $level, ['head_teacher_id' => 99999, 'capacity' => 0]))
            ->assertSessionHasErrors(['head_teacher_id', 'capacity']);
    }

    public function test_non_admin_cannot_manage_classrooms(): void
    {
        $year = SchoolYear::factory()->create();
        $level = Level::factory()->create();

        foreach (['teacher', 'guardian', 'student'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get(route('admin.classrooms.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.classrooms.store'), $this->payload($year, $level))->assertForbidden();
        }

        $this->assertDatabaseCount('classrooms', 0);
    }

    public function test_classroom_page_and_index_render(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'Terminale C']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.classrooms.index'))->assertOk()->assertSee('Terminale C');
        $this->actingAs($admin)->get(route('admin.classrooms.show', $classroom))->assertOk();
        $this->actingAs($admin)->get(route('admin.classrooms.edit', $classroom))->assertOk();
        $this->actingAs($admin)->get(route('admin.classrooms.create'))->assertOk();
    }

    public function test_searching_an_exact_level_name_filters_classrooms_by_that_level(): void
    {
        $year = SchoolYear::factory()->create();
        $ci = Level::factory()->create(['name' => 'CI', 'position' => 1]);
        $cp = Level::factory()->create(['name' => 'CP', 'position' => 2]);
        Classroom::factory()->create([
            'school_year_id' => $year->id,
            'level_id' => $ci->id,
            'name' => 'PERF26-CI',
        ]);
        Classroom::factory()->create([
            'school_year_id' => $year->id,
            'level_id' => $cp->id,
            'name' => 'PERF26-CP',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.classrooms.index', ['search' => 'CI']))
            ->assertOk()
            ->assertSee('PERF26-CI')
            ->assertDontSee('PERF26-CP');
    }
}
