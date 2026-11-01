<?php

namespace App\Services\Performance;

use App\Models\SchoolYear;
use Carbon\CarbonImmutable;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PerformanceAcademicDatasetProvisioner
{
    private const MARKER = 'PERF26';

    private const SUBJECTS = [
        'PF26FRAN' => ['name' => 'Français', 'specialty' => 'Français', 'coefficient' => 3, 'hours' => 5],
        'PF26MATH' => ['name' => 'Mathématiques', 'specialty' => 'Mathématiques', 'coefficient' => 3, 'hours' => 5],
        'PF26ANG' => ['name' => 'Anglais', 'specialty' => 'Anglais', 'coefficient' => 2, 'hours' => 3],
        'PF26HG' => ['name' => 'Histoire-Géographie', 'specialty' => 'Histoire-Géographie', 'coefficient' => 2, 'hours' => 3],
        'PF26SVT' => ['name' => 'Sciences de la Vie et de la Terre', 'specialty' => 'Sciences de la Vie et de la Terre', 'coefficient' => 2, 'hours' => 3],
        'PF26PC' => ['name' => 'Physique-Chimie', 'specialty' => 'Physique-Chimie', 'coefficient' => 2, 'hours' => 3],
        'PF26ECM' => ['name' => 'Éducation Civique et Morale', 'specialty' => 'Éducation sociale', 'coefficient' => 1, 'hours' => 2],
        'PF26EPS' => ['name' => 'Éducation Physique et Sportive', 'specialty' => 'Éducation physique et sportive', 'coefficient' => 1, 'hours' => 2],
        'PF26EST' => ['name' => 'Éducation Scientifique et Technologique', 'specialty' => 'Sciences et technologie', 'coefficient' => 2, 'hours' => 3],
        'PF26ART' => ['name' => 'Éducation Artistique', 'specialty' => 'Arts', 'coefficient' => 1, 'hours' => 2],
        'PF26ESP' => ['name' => 'Espagnol', 'specialty' => 'Espagnol', 'coefficient' => 1, 'hours' => 2],
        'PF26ICT' => ['name' => 'Informatique et TIC', 'specialty' => 'Informatique', 'coefficient' => 1, 'hours' => 2],
        'PF26PHI' => ['name' => 'Philosophie', 'specialty' => 'Philosophie', 'coefficient' => 2, 'hours' => 3],
        'PF26SOC' => ['name' => 'Éducation Sociale', 'specialty' => 'Éducation sociale', 'coefficient' => 1, 'hours' => 3],
    ];

    private const CURRICULUM = [
        'primary' => ['PF26FRAN', 'PF26MATH', 'PF26SOC', 'PF26EST', 'PF26ART', 'PF26EPS'],
        'college' => ['PF26FRAN', 'PF26MATH', 'PF26ANG', 'PF26HG', 'PF26SVT', 'PF26PC', 'PF26ECM', 'PF26EPS'],
        'lycee' => ['PF26FRAN', 'PF26MATH', 'PF26ANG', 'PF26HG', 'PF26SVT', 'PF26PC', 'PF26PHI', 'PF26EPS'],
    ];

    private const TEACHERS_PER_SPECIALTY = 5;

    /**
     * @return array<string, int>
     */
    public function populate(): array
    {
        $schoolYear = SchoolYear::current();

        if ($schoolYear === null) {
            throw new RuntimeException('Une année scolaire active est nécessaire.');
        }

        $classrooms = DB::table('classrooms')
            ->join('levels', 'levels.id', '=', 'classrooms.level_id')
            ->where('classrooms.name', 'like', self::MARKER.'-%')
            ->where('classrooms.school_year_id', $schoolYear->id)
            ->whereNull('classrooms.deleted_at')
            ->orderBy('classrooms.name')
            ->get([
                'classrooms.id',
                'classrooms.name',
                'levels.name as level_name',
                'levels.cycle',
            ]);

        if ($classrooms->isEmpty()) {
            throw new RuntimeException('Aucune classe PERF26 active trouvée pour l’année scolaire active.');
        }

        return DB::transaction(function () use ($classrooms, $schoolYear): array {
            $timestamp = now()->toDateTimeString();

            $subjectIds = [];

            foreach (self::SUBJECTS as $code => $subject) {
                $existingSubject = DB::table('subjects')->where('name', $subject['name'])->first();

                if ($existingSubject !== null) {
                    $subjectIds[$code] = $existingSubject->id;

                    continue;
                }

                $existingSubject = DB::table('subjects')->where('code', $code)->first();

                if ($existingSubject === null) {
                    $subjectIds[$code] = DB::table('subjects')->insertGetId([
                        'code' => $code,
                        'name' => $subject['name'],
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                } else {
                    DB::table('subjects')->where('id', $existingSubject->id)->update([
                        'name' => $subject['name'],
                        'updated_at' => $timestamp,
                    ]);
                    $subjectIds[$code] = $existingSubject->id;
                }
            }
            $specialties = collect(self::SUBJECTS)->pluck('specialty')->unique()->values();
            $teacherRows = [];

            foreach ($specialties as $specialtyIndex => $specialty) {
                for ($teacherNumber = 1; $teacherNumber <= self::TEACHERS_PER_SPECIALTY; $teacherNumber++) {
                    $number = $specialtyIndex * self::TEACHERS_PER_SPECIALTY + $teacherNumber;
                    $faker = FakerFactory::create('fr_FR');
                    $faker->seed($number);
                    $gender = $faker->randomElement(['M', 'F']);

                    $teacherRows[] = [
                        'matricule' => sprintf('PERF26-ENS-%03d', $number),
                        'first_name' => $gender === 'M' ? $faker->firstNameMale() : $faker->firstNameFemale(),
                        'last_name' => mb_strtoupper($faker->lastName()),
                        'gender' => $gender,
                        'phone' => '+229 01 '.fake()->numerify('## ## ## ##'),
                        'email' => sprintf('enseignant-perf26-%03d@example.test', $number),
                        'address' => fake('fr_FR')->streetAddress(),
                        'specialty' => $specialty,
                        'education_level' => fake()->randomElement(['licence', 'master']),
                        'hired_at' => CarbonImmutable::parse($schoolYear->start_date->toDateString())->subYears(
                            fake()->numberBetween(1, 15),
                        )->toDateString(),
                        'status' => 'active',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            $existingTeacherCount = DB::table('teachers')
                ->where('matricule', 'like', self::MARKER.'-ENS-%')
                ->count();

            if ($existingTeacherCount === 0) {
                foreach (array_chunk($teacherRows, 500) as $chunk) {
                    DB::table('teachers')->insert($chunk);
                }
            } elseif ($existingTeacherCount !== count($teacherRows)) {
                throw new RuntimeException('Le jeu PERF26 contient un nombre inattendu de profils enseignants; aucune donnée n’a été modifiée.');
            } else {
                foreach ($teacherRows as $teacher) {
                    DB::table('teachers')
                        ->where('matricule', $teacher['matricule'])
                        ->whereNull('user_id')
                        ->update([
                            'first_name' => $teacher['first_name'],
                            'gender' => $teacher['gender'],
                            'updated_at' => $timestamp,
                        ]);
                }
            }

            $teachersBySpecialty = DB::table('teachers')
                ->where('matricule', 'like', self::MARKER.'-ENS-%')
                ->orderBy('matricule')
                ->get(['id', 'specialty'])
                ->groupBy('specialty')
                ->map(fn ($teachers) => $teachers->values());
            $specialtyOffsets = [];
            $assignmentCount = 0;

            foreach ($classrooms as $classroom) {
                $subjectCodes = self::CURRICULUM[$classroom->cycle] ?? null;

                if ($subjectCodes === null) {
                    throw new RuntimeException("Cycle scolaire non reconnu pour le niveau {$classroom->level_name}.");
                }

                if (in_array($classroom->level_name, ['4ème', '3ème', 'Seconde'], true)) {
                    $subjectCodes[] = 'PF26ESP';
                    $subjectCodes[] = 'PF26ICT';
                }

                if (in_array($classroom->level_name, ['Première', 'Terminale'], true)) {
                    $subjectCodes[] = 'PF26ESP';
                    $subjectCodes[] = 'PF26ICT';
                }

                $assignmentRows = [];

                foreach ($subjectCodes as $subjectCode) {
                    $subject = self::SUBJECTS[$subjectCode];
                    $teacherPool = $teachersBySpecialty->get($subject['specialty']);

                    if ($teacherPool === null || $teacherPool->isEmpty()) {
                        throw new RuntimeException("Aucun enseignant fictif prévu pour {$subject['specialty']}.");
                    }

                    $teacherOffset = $specialtyOffsets[$subject['specialty']] ?? 0;
                    $teacher = $teacherPool[$teacherOffset % $teacherPool->count()];
                    $specialtyOffsets[$subject['specialty']] = $teacherOffset + 1;
                    $assignmentRows[] = [
                        'classroom_id' => $classroom->id,
                        'subject_id' => $subjectIds[$subjectCode],
                        'teacher_id' => $teacher->id,
                        'coefficient' => $subject['coefficient'],
                        'weekly_hours' => $subject['hours'],
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                foreach ($assignmentRows as $assignment) {
                    $existingAssignment = DB::table('teaching_assignments')
                        ->where('classroom_id', $assignment['classroom_id'])
                        ->where('subject_id', $assignment['subject_id'])
                        ->first();

                    if ($existingAssignment === null) {
                        DB::table('teaching_assignments')->insert($assignment);
                    } else {
                        DB::table('teaching_assignments')
                            ->where('id', $existingAssignment->id)
                            ->update([
                                'teacher_id' => $assignment['teacher_id'],
                                'coefficient' => $assignment['coefficient'],
                                'weekly_hours' => $assignment['weekly_hours'],
                                'updated_at' => $timestamp,
                            ]);
                    }

                    $assignmentCount++;
                }

                $this->removeLegacyPlaceholderAssignments(
                    $classroom->id,
                    $subjectIds,
                    $timestamp,
                    $classroom->cycle === 'primary',
                    in_array('PF26HG', $subjectCodes, true) ? 'PF26HG' : 'PF26SOC',
                );
            }

            return [
                'teachers' => count($teacherRows),
                'subjects' => count(self::SUBJECTS),
                'classes_with_curriculum' => $classrooms->count(),
                'teaching_assignments' => $assignmentCount,
            ];
        });
    }

    /**
     * Existing PERF26 grades remain attached to their evaluations while placeholder
     * assignments are replaced by the curriculum subject assignment for the class.
     *
     * @param  array<string, int>  $subjectIds
     */
    private function removeLegacyPlaceholderAssignments(
        int $classroomId,
        array $subjectIds,
        string $timestamp,
        bool $isPrimary,
        string $historyCurriculumCode,
    ): void {
        $legacyMappings = [
            'PF26MATH' => 'PF26MATH',
            'PF26HIST' => $historyCurriculumCode,

        ];

        if ($isPrimary) {
            $legacyMappings['PF26SVT'] = 'PF26EST';
        }

        foreach ($legacyMappings as $legacyCode => $curriculumCode) {
            $legacySubject = DB::table('subjects')->where('code', $legacyCode)->first();
            $curriculumSubjectId = $subjectIds[$curriculumCode];

            if ($legacySubject === null || (int) $legacySubject->id === $curriculumSubjectId) {
                continue;
            }

            $legacyAssignment = DB::table('teaching_assignments')
                ->where('classroom_id', $classroomId)
                ->where('subject_id', $legacySubject->id)
                ->first();

            if ($legacyAssignment === null) {
                continue;
            }

            $curriculumAssignment = DB::table('teaching_assignments')
                ->where('classroom_id', $classroomId)
                ->where('subject_id', $curriculumSubjectId)
                ->first();

            if ($curriculumAssignment === null) {
                throw new RuntimeException('Une matière pédagogique PERF26 n’a pas pu être affectée à une classe.');
            }

            if (DB::table('schedules')->where('teaching_assignment_id', $legacyAssignment->id)->exists()) {
                throw new RuntimeException('Un emploi du temps utilise encore une ancienne affectation PERF26; aucune donnée n’a été supprimée.');
            }

            $legacySessions = DB::table('attendance_sessions')
                ->where('classroom_id', $classroomId)
                ->where('subject_id', $legacySubject->id)
                ->orderBy('session_date')
                ->orderBy('starts_at')
                ->get();

            foreach ($legacySessions as $legacySession) {
                $startsAt = $legacySession->starts_at;
                $endsAt = $legacySession->ends_at;
                $slotTaken = DB::table('attendance_sessions')
                    ->where('classroom_id', $classroomId)
                    ->where('subject_id', $curriculumSubjectId)
                    ->where('session_date', $legacySession->session_date)
                    ->where('starts_at', $startsAt)
                    ->where('id', '!=', $legacySession->id)
                    ->exists();

                if ($slotTaken) {
                    $availableStart = collect(['06:00:00', '07:00:00', '16:00:00', '17:00:00', '18:00:00'])
                        ->first(fn (string $candidate): bool => ! DB::table('attendance_sessions')
                            ->where('classroom_id', $classroomId)
                            ->where('subject_id', $curriculumSubjectId)
                            ->where('session_date', $legacySession->session_date)
                            ->where('starts_at', $candidate)
                            ->exists());

                    if ($availableStart === null) {
                        throw new RuntimeException('Aucun créneau libre pour conserver une ancienne séance PERF26.');
                    }

                    $startsAt = $availableStart;
                    $endsAt = CarbonImmutable::parse($availableStart)->addMinutes(30)->format('H:i:s');
                }

                DB::table('attendance_sessions')
                    ->where('id', $legacySession->id)
                    ->update([
                        'subject_id' => $curriculumSubjectId,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'updated_at' => $timestamp,
                    ]);
            }

            DB::table('evaluations')
                ->where('teaching_assignment_id', $legacyAssignment->id)
                ->update([
                    'teaching_assignment_id' => $curriculumAssignment->id,
                    'updated_at' => $timestamp,
                ]);

            DB::table('teaching_assignments')->where('id', $legacyAssignment->id)->delete();
        }
    }
}
