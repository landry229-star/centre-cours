<?php

namespace Tests\Concerns;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Jeu de données des tests de présences : une année ACTIVE qui contient aujourd'hui, une classe active,
 * un enseignant (avec son compte), une matière, l'affectation et N élèves inscrits depuis un mois.
 */
trait CreatesAttendance
{
    private ?SchoolYear $attendanceYear = null;

    protected function attendanceYear(): SchoolYear
    {
        return $this->attendanceYear ??= SchoolYear::factory()->active()->create([
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(8)->toDateString(),
        ]);
    }

    /**
     * @return array{classroom: Classroom, subject: Subject, teacher: Teacher, user: User, assignment: TeachingAssignment, enrollments: Collection}
     */
    protected function attendanceSetup(int $students = 3): array
    {
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $classroom = Classroom::factory()->create(['school_year_id' => $this->attendanceYear()->id]);
        $subject = Subject::factory()->create();
        $assignment = TeachingAssignment::factory()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $enrollments = collect(range(1, max($students, 0)))->take($students)->map(fn () => Enrollment::factory()->create([
            'student_id' => Student::factory()->create()->id,
            'classroom_id' => $classroom->id,
            'enrolled_at' => now()->subMonth()->toDateString(),
        ]));

        return compact('classroom', 'subject', 'teacher', 'user', 'assignment', 'enrollments');
    }

    /** Corps d'une feuille d'appel : [enrollment_id => ['status' => ...]]. */
    protected function sheet(Collection $enrollments, string $status = 'present'): array
    {
        return ['entries' => $enrollments->mapWithKeys(fn ($e) => [$e->id => ['status' => $status]])->all()];
    }
}
