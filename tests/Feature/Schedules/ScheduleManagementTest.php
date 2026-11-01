<?php

namespace Tests\Feature\Schedules;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_weekly_schedule_with_the_selected_day_and_room(): void
    {
        $setup = $this->scheduleSetup();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.schedules.create'))
            ->assertOk()
            ->assertSee('Ajouter une séance');

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($setup, [
                'day_of_week' => 'sunday',
                'starts_at' => '08:30',
                'ends_at' => '10:00',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $schedule = Schedule::query()->firstOrFail();
        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'classroom_id' => $setup['classroom']->id,
            'teaching_assignment_id' => $setup['assignment']->id,
            'room_id' => $setup['room']->id,
            'day_of_week' => 'sunday',
            'starts_at' => '08:30:00',
            'ends_at' => '10:00:00',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('Dimanche')
            ->assertSee($setup['room']->name);

        $this->actingAs($admin)
            ->get(route('admin.schedules.edit', $schedule))
            ->assertOk()
            ->assertSee('Modifier une séance');
    }

    public function test_admin_can_filter_the_schedule_by_teacher(): void
    {
        $setup = $this->scheduleSetup();
        $admin = User::factory()->admin()->create();
        $this->createSchedule($setup, $admin);

        $otherTeacher = $this->teacher();
        $otherClassroom = Classroom::factory()->create(['school_year_id' => $setup['year']->id]);
        $otherSetup = [
            'year' => $setup['year'],
            'classroom' => $otherClassroom,
            'assignment' => $this->assignment($otherClassroom, $otherTeacher),
            'room' => Room::factory()->create(),
        ];
        $this->createSchedule($otherSetup, $admin);

        $this->actingAs($admin)
            ->get(route('admin.schedules.index', [
                'school_year_id' => $setup['year']->id,
                'teacher_id' => $setup['teacher']->id,
            ]))
            ->assertOk()
            ->assertViewHas('schedules', fn ($schedules): bool => $schedules->total() === 1
                && $schedules->first()->assignment->teacher_id === $setup['teacher']->id);
    }

    public function test_a_class_cannot_have_overlapping_schedules(): void
    {
        $setup = $this->scheduleSetup();
        $admin = User::factory()->admin()->create();
        $this->createSchedule($setup, $admin);

        $otherTeacher = $this->teacher();
        $otherAssignment = $this->assignment($setup['classroom'], $otherTeacher);
        $otherRoom = Room::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($setup, [
                'assignment_id' => $otherAssignment->id,
                'room_id' => $otherRoom->id,
                'starts_at' => '08:30',
                'ends_at' => '09:30',
            ]))
            ->assertSessionHas('error', 'Cette classe a déjà un cours à cet horaire.');

        $this->assertSame(1, Schedule::count());
    }

    public function test_an_enseignant_cannot_teach_two_classes_at_the_same_time(): void
    {
        $setup = $this->scheduleSetup();
        $admin = User::factory()->admin()->create();
        $this->createSchedule($setup, $admin);

        $otherClassroom = Classroom::factory()->create(['school_year_id' => $setup['year']->id]);
        $otherAssignment = $this->assignment($otherClassroom, $setup['teacher']);
        $otherRoom = Room::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($setup, [
                'assignment_id' => $otherAssignment->id,
                'room_id' => $otherRoom->id,
                'starts_at' => '08:30',
                'ends_at' => '09:30',
            ]))
            ->assertSessionHas('error', 'Cet enseignant a déjà un cours à cet horaire.');

        $this->assertSame(1, Schedule::count());
    }

    public function test_a_room_cannot_be_used_by_two_classes_at_the_same_time(): void
    {
        $setup = $this->scheduleSetup();
        $admin = User::factory()->admin()->create();
        $this->createSchedule($setup, $admin);

        $otherClassroom = Classroom::factory()->create(['school_year_id' => $setup['year']->id]);
        $otherAssignment = $this->assignment($otherClassroom, $this->teacher());

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($setup, [
                'assignment_id' => $otherAssignment->id,
                'starts_at' => '08:30',
                'ends_at' => '09:30',
            ]))
            ->assertSessionHas('error', 'Cette salle est déjà occupée à cet horaire.');

        $this->assertSame(1, Schedule::count());
    }

    public function test_adjacent_sessions_and_sessions_on_different_days_are_allowed(): void
    {
        $setup = $this->scheduleSetup();
        $admin = User::factory()->admin()->create();
        $this->createSchedule($setup, $admin);

        $this->actingAs($admin)->post(route('admin.schedules.store'), $this->payload($setup, [
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.schedules.store'), $this->payload($setup, [
            'day_of_week' => 'tuesday',
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(3, Schedule::count());
    }

    public function test_schedule_can_be_updated_without_conflicting_with_itself(): void
    {
        $setup = $this->scheduleSetup();
        $admin = User::factory()->admin()->create();
        $schedule = $this->createSchedule($setup, $admin);

        $this->actingAs($admin)
            ->put(route('admin.schedules.update', $schedule), $this->payload($setup, [
                'starts_at' => '09:00',
                'ends_at' => '10:00',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('09:00', $schedule->fresh()->startTime());
        $this->assertSame(1, Schedule::count());
    }

    public function test_teacher_guardian_and_student_only_see_schedules_they_are_entitled_to(): void
    {
        $mine = $this->scheduleSetup();
        $other = $this->scheduleSetup(0, $mine['year']);
        $admin = User::factory()->admin()->create();
        $mySchedule = $this->createSchedule($mine, $admin);
        $this->createSchedule($other, $admin);

        $this->actingAs($mine['teacherUser'])
            ->get(route('teacher.schedules.index'))
            ->assertOk()
            ->assertSee($mine['classroom']->name)
            ->assertDontSee($other['classroom']->name);

        $guardianUser = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create();
        $guardian->forceFill(['user_id' => $guardianUser->id])->save();
        $child = $mine['student'];
        $child->forceFill(['guardian_id' => $guardian->id])->save();

        $this->actingAs($guardianUser)
            ->get(route('guardian.schedules.index'))
            ->assertOk()
            ->assertSee($mine['classroom']->name)
            ->assertDontSee($other['classroom']->name);

        $studentUser = User::factory()->student()->create();
        $child->forceFill(['user_id' => $studentUser->id])->save();
        $this->actingAs($studentUser)
            ->get(route('student.schedules.index'))
            ->assertOk()
            ->assertSee($mySchedule->room->name)
            ->assertDontSee($other['room']->name);
    }

    public function test_non_admin_roles_cannot_manage_schedules(): void
    {
        $setup = $this->scheduleSetup(0);

        foreach ([
            User::factory()->teacher()->create(),
            User::factory()->guardian()->create(),
            User::factory()->student()->create(),
        ] as $user) {
            $this->actingAs($user)
                ->post(route('admin.schedules.store'), $this->payload($setup))
                ->assertForbidden();
        }

        $this->assertSame(0, Schedule::count());
    }

    public function test_assignment_teacher_cannot_change_while_it_has_a_schedule(): void
    {
        $setup = $this->scheduleSetup(0);
        $admin = User::factory()->admin()->create();
        $this->createSchedule($setup, $admin);
        $newTeacher = $this->teacher();

        $this->actingAs($admin)
            ->put(route('admin.assignments.update', $setup['assignment']), [
                'subject_id' => $setup['assignment']->subject_id,
                'teacher_id' => $newTeacher->id,
                'coefficient' => 1,
                'weekly_hours' => 3,
            ])
            ->assertSessionHas('error');

        $this->assertSame($setup['teacher']->id, $setup['assignment']->fresh()->teacher_id);
    }

    public function test_invalid_time_range_is_rejected_before_saving(): void
    {
        $setup = $this->scheduleSetup();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.schedules.store'), $this->payload($setup, [
                'starts_at' => '10:00',
                'ends_at' => '10:00',
            ]))
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(0, Schedule::count());
    }

    public function test_database_rejects_a_schedule_ending_before_it_starts(): void
    {
        $setup = $this->scheduleSetup(0);
        $admin = User::factory()->admin()->create();

        $this->expectException(QueryException::class);

        DB::table('schedules')->insert([
            'classroom_id' => $setup['classroom']->id,
            'teaching_assignment_id' => $setup['assignment']->id,
            'room_id' => $setup['room']->id,
            'created_by' => $admin->id,
            'day_of_week' => 'monday',
            'starts_at' => '10:00',
            'ends_at' => '09:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{year: SchoolYear, classroom: Classroom, teacherUser: User, teacher: Teacher, assignment: TeachingAssignment, room: Room, student: Student} */
    private function scheduleSetup(int $studentCount = 1, ?SchoolYear $year = null): array
    {
        $year ??= SchoolYear::factory()->active()->create([
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(8)->toDateString(),
        ]);
        $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
        $teacherUser = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create();
        $teacher->forceFill(['user_id' => $teacherUser->id])->save();
        $assignment = $this->assignment($classroom, $teacher);
        $room = Room::factory()->create();
        $student = Student::factory()->create();

        if ($studentCount > 0) {
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'classroom_id' => $classroom->id,
                'school_year_id' => $year->id,
                'enrolled_at' => now()->subDays(3)->toDateString(),
            ]);
        }

        return compact('year', 'classroom', 'teacherUser', 'teacher', 'assignment', 'room', 'student');
    }

    private function teacher(): Teacher
    {
        return Teacher::factory()->create();
    }

    private function assignment(Classroom $classroom, Teacher $teacher): TeachingAssignment
    {
        return $classroom->assignments()->create([
            'subject_id' => Subject::factory()->create()->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 1,
        ]);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $setup, array $overrides = []): array
    {
        return array_merge([
            'assignment_id' => $setup['assignment']->id,
            'room_id' => $setup['room']->id,
            'day_of_week' => 'monday',
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ], $overrides);
    }

    private function createSchedule(array $setup, User $admin): Schedule
    {
        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($setup))
            ->assertSessionHasNoErrors();

        return Schedule::query()->latest('id')->firstOrFail()->load('room');
    }
}
