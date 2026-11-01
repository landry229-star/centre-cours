<?php

namespace Tests\Feature\Enrollments;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\SchoolYear;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garanties portées par PostgreSQL lui-même, indépendantes du code PHP.
 * Chaque test d'erreur ne contient QU'UNE requête fautive (PostgreSQL annule la transaction ensuite).
 */
class EnrollmentConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_composite_foreign_key_forbids_a_year_that_differs_from_the_classroom(): void
    {
        $classroom = Classroom::factory()->create();
        $otherYear = SchoolYear::factory()->create();

        $this->expectException(QueryException::class);

        Enrollment::factory()->create(['classroom_id' => $classroom->id, 'school_year_id' => $otherYear->id]);
    }

    public function test_only_one_active_enrollment_per_student_and_year(): void
    {
        $first = Enrollment::factory()->create();

        $this->expectException(QueryException::class);

        Enrollment::factory()->create(['student_id' => $first->student_id, 'classroom_id' => $first->classroom_id]);
    }

    public function test_history_rows_are_unlimited_next_to_one_active_row(): void
    {
        $student = Student::factory()->create();
        $classroom = Classroom::factory()->create();

        Enrollment::factory()->withdrawn()->count(2)->create(['student_id' => $student->id, 'classroom_id' => $classroom->id]);
        Enrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id]);

        $this->assertSame(3, Enrollment::where('student_id', $student->id)->count());
    }

    public function test_active_enrollment_cannot_have_an_end_date(): void
    {
        $this->expectException(QueryException::class);

        Enrollment::factory()->create(['ended_at' => now()->toDateString()]);
    }

    public function test_ended_enrollment_must_have_an_end_date(): void
    {
        $this->expectException(QueryException::class);

        Enrollment::factory()->create(['status' => 'withdrawn', 'ended_at' => null]);
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $this->expectException(QueryException::class);

        Enrollment::factory()->create([
            'status' => 'withdrawn',
            'enrolled_at' => now()->toDateString(),
            'ended_at' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_school_years_cannot_overlap(): void
    {
        SchoolYear::factory()->create(['label' => '2040-2041', 'start_date' => '2040-10-01', 'end_date' => '2041-07-31']);

        $this->expectException(QueryException::class);

        SchoolYear::factory()->create(['label' => '2041-2042', 'start_date' => '2041-06-01', 'end_date' => '2042-05-01']);
    }
}
