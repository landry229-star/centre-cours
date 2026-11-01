<?php

namespace App\Services\Performance;

use App\Enums\Role;
use App\Models\Level;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PerformanceDatasetProvisioner
{
    private const MARKER = 'PERF26';

    private const SUBJECTS = [
        ['code' => 'PF26MATH', 'name' => 'Performance - Mathématiques'],
        ['code' => 'PF26FRAN', 'name' => 'Performance - Français'],
        ['code' => 'PF26HIST', 'name' => 'Performance - Histoire'],
        ['code' => 'PF26SVT', 'name' => 'Performance - Sciences'],
    ];

    private const BATCH_SIZE = 1000;

    /**
     * @return array<string, int>
     */
    public function populate(int $studentCount, int $classroomCount): array
    {
        if (
            $studentCount < 1
            || $studentCount > 20000
            || $classroomCount < (int) ceil($studentCount / 40)
            || $classroomCount > min($studentCount, 800)
        ) {
            throw new RuntimeException('Le volume demandé est invalide ou dépasse les limites de la recette locale.');
        }

        $schoolYear = SchoolYear::current();
        $adminId = User::query()->where('role', Role::Admin->value)->value('id');
        $teacher = Teacher::query()
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->first();
        $levels = Level::query()->orderBy('position')->pluck('id')->all();

        if ($schoolYear === null || $adminId === null || $teacher === null || $levels === []) {
            throw new RuntimeException('Une année active, un administrateur, un enseignant actif avec compte et des niveaux sont requis.');
        }

        $fees = DB::table('fee_schedules')
            ->where('school_year_id', $schoolYear->id)
            ->pluck('monthly_amount', 'level_id')
            ->map(fn (int|string $amount): int => (int) $amount);

        if (collect($levels)->contains(fn (int $levelId): bool => ! $fees->has($levelId))) {
            throw new RuntimeException('Chaque niveau utilisé doit avoir un tarif défini pour l’année scolaire active.');
        }

        $schoolYearStart = CarbonImmutable::parse($schoolYear->start_date->toDateString());
        $schoolYearEnd = CarbonImmutable::parse($schoolYear->end_date->toDateString());
        $sessionDates = $this->pastWeekdays($schoolYearStart, $schoolYearEnd);

        if ($sessionDates === []) {
            throw new RuntimeException('L’année active ne contient encore aucun jour scolaire passé pour la recette.');
        }

        return DB::transaction(function () use (
            $adminId,
            $classroomCount,
            $fees,
            $levels,
            $schoolYear,
            $schoolYearStart,
            $sessionDates,
            $studentCount,
            $teacher,
        ): array {
            $this->assertDatasetDoesNotExist();

            $timestamp = now()->toDateTimeString();
            $guardianEmails = [];
            $guardianRows = [];

            for ($studentNumber = 1; $studentNumber <= $studentCount; $studentNumber++) {
                $guardianEmails[$studentNumber] = sprintf('parent-perf26-%05d@example.test', $studentNumber);
                $guardianRows[] = [
                    'first_name' => fake('fr_FR')->firstName(),
                    'last_name' => mb_strtoupper(fake('fr_FR')->lastName()),
                    'phone' => '+229 01 '.fake()->numerify('## ## ## ##'),
                    'email' => $guardianEmails[$studentNumber],
                    'address' => fake('fr_FR')->streetAddress(),
                    'profession' => fake('fr_FR')->jobTitle(),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            $this->insertRows('guardians', $guardianRows);

            $guardianIds = DB::table('guardians')
                ->whereIn('email', $guardianEmails)
                ->pluck('id', 'email');
            $studentRows = [];

            for ($studentNumber = 1; $studentNumber <= $studentCount; $studentNumber++) {
                $studentRows[] = [
                    'guardian_id' => $guardianIds[$guardianEmails[$studentNumber]],
                    'matricule' => sprintf('PERF26-%05d', $studentNumber),
                    'first_name' => fake('fr_FR')->firstName(),
                    'last_name' => mb_strtoupper(fake('fr_FR')->lastName()),
                    'gender' => fake()->randomElement(['M', 'F']),
                    'birth_date' => fake()->dateTimeBetween(
                        $schoolYearStart->subYears(18)->toDateString(),
                        $schoolYearStart->subYears(6)->toDateString(),
                    )->format('Y-m-d'),
                    'birth_place' => fake('fr_FR')->city(),
                    'phone' => null,
                    'address' => fake('fr_FR')->streetAddress(),
                    'guardian_relationship' => fake()->randomElement(['father', 'mother', 'guardian', 'uncle', 'aunt', 'sibling', 'other']),
                    'emergency_contact_name' => fake('fr_FR')->name(),
                    'emergency_contact_phone' => '+229 01 '.fake()->numerify('## ## ## ##'),
                    'status' => 'active',
                    'qr_token' => Str::random(40),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            $this->insertRows('students', $studentRows);

            $classNames = [];
            $classRows = [];

            for ($classNumber = 1; $classNumber <= $classroomCount; $classNumber++) {
                $name = sprintf('%s-%03d', self::MARKER, $classNumber);
                $classNames[] = $name;
                $classRows[] = [
                    'school_year_id' => $schoolYear->id,
                    'level_id' => $levels[($classNumber - 1) % count($levels)],
                    'name' => $name,
                    'capacity' => 40,
                    'room_id' => null,
                    'head_teacher_id' => $teacher->id,
                    'status' => 'active',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            $this->insertRows('classrooms', $classRows);
            $classrooms = DB::table('classrooms')
                ->whereIn('name', $classNames)
                ->orderBy('name')
                ->get(['id', 'name', 'level_id'])
                ->keyBy('name');
            $subjectIds = [];

            foreach (self::SUBJECTS as $subject) {
                $subjectIds[] = DB::table('subjects')->insertGetId([
                    ...$subject,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            $assignmentRows = [];

            foreach ($classrooms as $classroom) {
                foreach ($subjectIds as $subjectIndex => $subjectId) {
                    $assignmentRows[] = [
                        'classroom_id' => $classroom->id,
                        'subject_id' => $subjectId,
                        'teacher_id' => $teacher->id,
                        'coefficient' => $subjectIndex + 1,
                        'weekly_hours' => 2,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            $this->insertRows('teaching_assignments', $assignmentRows);
            $assignments = DB::table('teaching_assignments')
                ->whereIn('classroom_id', $classrooms->pluck('id'))
                ->whereIn('subject_id', $subjectIds)
                ->get(['id', 'classroom_id', 'subject_id'])
                ->keyBy(fn (object $assignment): string => "{$assignment->classroom_id}:{$assignment->subject_id}");
            $students = DB::table('students')
                ->where('matricule', 'like', self::MARKER.'-%')
                ->orderBy('matricule')
                ->get(['id', 'matricule', 'guardian_id']);
            $enrollmentRows = [];

            foreach ($students as $studentIndex => $student) {
                $classroom = $classrooms->get($classNames[intdiv($studentIndex * $classroomCount, $studentCount)]);
                $enrollmentRows[] = [
                    'student_id' => $student->id,
                    'classroom_id' => $classroom->id,
                    'school_year_id' => $schoolYear->id,
                    'enrolled_at' => $schoolYearStart->toDateString(),
                    'ended_at' => null,
                    'status' => 'active',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            $this->insertRows('enrollments', $enrollmentRows);
            $enrollments = DB::table('enrollments')
                ->join('students', 'students.id', '=', 'enrollments.student_id')
                ->where('students.matricule', 'like', self::MARKER.'-%')
                ->get([
                    'enrollments.id',
                    'enrollments.classroom_id',
                    'students.id as student_id',
                    'students.matricule',
                    'students.guardian_id',
                ]);
            $enrollmentsByClassroom = $enrollments->groupBy('classroom_id');
            $sessionRows = [];

            foreach ($classrooms as $classroom) {
                foreach ($subjectIds as $subjectIndex => $subjectId) {
                    foreach ($sessionDates as $date) {
                        $startHour = [8, 10, 13, 15][$subjectIndex];
                        $startsAt = sprintf('%02d:00:00', $startHour);
                        $endsAt = sprintf('%02d:30:00', $startHour + 1);
                        $sessionRows[] = [
                            'classroom_id' => $classroom->id,
                            'subject_id' => $subjectId,
                            'teacher_id' => $teacher->id,
                            'session_date' => $date,
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'created_by' => $adminId,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                }
            }

            $this->insertRows('attendance_sessions', $sessionRows);
            $sessions = DB::table('attendance_sessions')
                ->whereIn('classroom_id', $classrooms->pluck('id'))
                ->whereIn('subject_id', $subjectIds)
                ->whereIn('session_date', $sessionDates)
                ->get(['id', 'classroom_id', 'subject_id', 'session_date']);
            $sessionDateIndexes = array_flip($sessionDates);
            $subjectIndexes = array_flip($subjectIds);
            $attendanceRows = [];
            $attendanceCount = 0;

            foreach ($sessions as $session) {
                $dateIndex = $sessionDateIndexes[$session->session_date];
                $subjectIndex = $subjectIndexes[$session->subject_id];

                foreach ($enrollmentsByClassroom->get($session->classroom_id, collect()) as $enrollment) {
                    $pattern = ((int) substr($enrollment->matricule, -5) + $dateIndex + $subjectIndex) % 100;
                    $status = match (true) {
                        $pattern < 85 => 'present',
                        $pattern < 91 => 'late',
                        $pattern < 98 => 'absent',
                        default => 'excused',
                    };

                    $attendanceRows[] = [
                        'attendance_session_id' => $session->id,
                        'classroom_id' => $session->classroom_id,
                        'enrollment_id' => $enrollment->id,
                        'status' => $status,
                        'arrived_at' => $status === 'late' ? '08:15:00' : null,
                        'note' => null,
                        'marked_by' => $adminId,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    if (count($attendanceRows) === self::BATCH_SIZE) {
                        DB::table('attendances')->insert($attendanceRows);
                        $attendanceCount += count($attendanceRows);
                        $attendanceRows = [];
                    }
                }
            }

            if ($attendanceRows !== []) {
                DB::table('attendances')->insert($attendanceRows);
                $attendanceCount += count($attendanceRows);
            }

            $evaluationRows = [];

            foreach ($classrooms as $classroom) {
                foreach ($subjectIds as $subjectIndex => $subjectId) {
                    $assignment = $assignments->get("{$classroom->id}:{$subjectId}");
                    $evaluationRows[] = [
                        'classroom_id' => $classroom->id,
                        'teaching_assignment_id' => $assignment->id,
                        'teacher_id' => $teacher->id,
                        'created_by' => $adminId,
                        'title' => self::MARKER.' Évaluation '.$subjectIndex,
                        'type' => 'quiz',
                        'evaluation_date' => $sessionDates[0],
                        'coefficient' => 1,
                        'subject_coefficient' => $subjectIndex + 1,
                        'published_at' => $timestamp,
                        'published_by' => $adminId,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            $this->insertRows('evaluations', $evaluationRows);
            $evaluations = DB::table('evaluations')
                ->whereIn('classroom_id', $classrooms->pluck('id'))
                ->where('title', 'like', self::MARKER.' Évaluation%')
                ->get(['id', 'classroom_id', 'teaching_assignment_id', 'subject_coefficient']);
            $gradeRows = [];

            foreach ($evaluations as $evaluation) {
                foreach ($enrollmentsByClassroom->get($evaluation->classroom_id, collect()) as $enrollment) {
                    $studentNumber = (int) substr($enrollment->matricule, -5);
                    $score = (($studentNumber * 17 + $evaluation->subject_coefficient * 31) % 2001) / 100;
                    $gradeRows[] = [
                        'evaluation_id' => $evaluation->id,
                        'enrollment_id' => $enrollment->id,
                        'classroom_id' => $evaluation->classroom_id,
                        'entered_by' => $teacher->user_id,
                        'score' => number_format($score, 2, '.', ''),
                        'comment' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            $this->insertRows('grades', $gradeRows);
            $paymentRows = [];
            $paymentMonth = $schoolYearStart->startOfMonth()->toDateString();
            $paidAt = now()->toDateString();

            foreach ($enrollments as $enrollmentIndex => $enrollment) {
                $paymentPattern = $enrollmentIndex % 10;

                if ($paymentPattern >= 7) {
                    continue;
                }

                $studentNumber = (int) substr($enrollment->matricule, -5);
                $levelId = $classrooms
                    ->firstWhere('id', $enrollment->classroom_id)
                    ->level_id;
                $monthlyAmount = $fees[$levelId];
                $amount = $paymentPattern < 4 ? $monthlyAmount : intdiv($monthlyAmount, 2);
                $paymentRows[] = [
                    'enrollment_id' => $enrollment->id,
                    'month' => $paymentMonth,
                    'amount' => $amount,
                    'method' => $paymentPattern < 4 ? 'cash' : 'mobile_money',
                    'reference' => $paymentPattern < 4 ? null : sprintf('PF26-TX-%05d', $studentNumber),
                    'payer_name' => sprintf('Parent fictif PERF26-%05d', $studentNumber),
                    'paid_at' => $paidAt,
                    'receipt_number' => sprintf('PF26-%06d', $studentNumber),
                    'recorded_by' => $adminId,
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancel_reason' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            $this->insertRows('payments', $paymentRows);

            return [
                'guardians' => $studentCount,
                'students' => $studentCount,
                'classrooms' => $classroomCount,
                'teaching_assignments' => $classroomCount * count($subjectIds),
                'enrollments' => $studentCount,
                'attendance_sessions' => count($sessionRows),
                'attendances' => $attendanceCount,
                'evaluations' => $classroomCount * count($subjectIds),
                'grades' => count($gradeRows),
                'payments' => count($paymentRows),
            ];
        });
    }

    /**
     * @param  list<array<string, int|string|null>>  $rows
     */
    private function insertRows(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * @return list<string>
     */
    private function pastWeekdays(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $today = today()->toImmutable();
        $lastDate = $end->lt($today) ? $end : $today;
        $dates = [];

        for ($date = $start; $date->lte($lastDate) && count($dates) < 20; $date = $date->addDay()) {
            if ($date->isWeekday()) {
                $dates[] = $date->toDateString();
            }
        }

        return $dates;
    }

    private function assertDatasetDoesNotExist(): void
    {
        if (
            DB::table('students')->where('matricule', 'like', self::MARKER.'-%')->exists()
            || DB::table('classrooms')->where('name', 'like', self::MARKER.'-%')->exists()
            || DB::table('guardians')->where('email', 'like', 'parent-perf26-%@example.test')->exists()
            || DB::table('subjects')->where('code', 'like', 'PF26%')->exists()
        ) {
            throw new RuntimeException('Un jeu PERF26 existe déjà. Aucun enregistrement n’a été modifié; ne relancez pas le chargement.');
        }
    }
}
