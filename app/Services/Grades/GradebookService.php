<?php

namespace App\Services\Grades;

use App\Enums\ClassroomStatus;
use App\Enums\TeacherStatus;
use App\Events\GradeRecorded;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GradebookService
{
    public function __construct(private SchoolYearPeriodService $periods) {}

    /** @param array{title: string, type: string, evaluation_date: string, coefficient: int|string} $data */
    public function createEvaluation(TeachingAssignment $assignment, array $data, User $actor): Evaluation
    {
        return DB::transaction(function () use ($assignment, $data, $actor) {
            $assignment = TeachingAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $classroom = Classroom::query()
                ->whereKey($assignment->classroom_id)
                ->lockForUpdate()
                ->firstOrFail();
            $schoolYear = SchoolYear::query()->whereKey($classroom->school_year_id)->lockForUpdate()->firstOrFail();

            if ($classroom->status !== ClassroomStatus::Active || ! $schoolYear->is_active) {
                throw new DomainException('Les notes ne peuvent être créées que dans une classe et une année scolaire actives.');
            }

            if (! $actor->isAdmin() && ($actor->teacher === null || $assignment->teacher_id !== $actor->teacher->id)) {
                throw new DomainException('Cette affectation ne vous appartient pas.');
            }

            $teacher = $assignment->teacher;

            if ($teacher === null || $teacher->status !== TeacherStatus::Active) {
                throw new DomainException("L'enseignant affecté n'est pas actif.");
            }

            $date = CarbonImmutable::parse($data['evaluation_date']);

            if ($date->toDateString() < $schoolYear->start_date->toDateString()
                || $date->toDateString() > $schoolYear->end_date->toDateString()) {
                throw new DomainException("La date de l'évaluation doit être comprise dans l'année scolaire de la classe.");
            }

            $this->periods->ensureDefaults($schoolYear);

            $evaluation = new Evaluation;
            $evaluation->forceFill([
                'classroom_id' => $classroom->id,
                'teaching_assignment_id' => $assignment->id,
                'teacher_id' => $assignment->teacher_id,
                'created_by' => $actor->id,
                'title' => $data['title'],
                'type' => $data['type'],
                'evaluation_date' => $date->toDateString(),
                'coefficient' => (int) $data['coefficient'],
                'subject_coefficient' => $assignment->coefficient,
            ])->save();

            return $evaluation;
        });
    }

    /**
     * @param  array<int|string, array{score: int|float|string|null, comment?: string|null}>  $entries
     */
    public function recordGrades(Evaluation $evaluation, array $entries, User $actor): void
    {
        DB::transaction(function () use ($evaluation, $entries, $actor): void {
            $evaluation = Evaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if (! $actor->isAdmin()
                && (! $actor->isTeacher() || $actor->teacher === null || $actor->teacher->id !== $evaluation->teacher_id)) {
                throw new DomainException('Seul l’enseignant propriétaire ou l’administration peut saisir des notes.');
            }

            if ($evaluation->published_at !== null) {
                throw new DomainException('Les notes publiées ne peuvent plus être saisies par cette action.');
            }

            $roster = $this->rosterQuery($evaluation)->lockForUpdate()->get()->keyBy('id');
            $submittedIds = array_map('intval', array_keys($entries));
            $unknownIds = array_diff($submittedIds, $roster->keys()->map(fn ($id) => (int) $id)->all());

            if ($unknownIds !== []) {
                throw new DomainException('Une ou plusieurs inscriptions ne correspondent pas à cette évaluation.');
            }

            foreach ($entries as $enrollmentId => $entry) {
                $grade = Grade::query()
                    ->where('evaluation_id', $evaluation->id)
                    ->where('enrollment_id', (int) $enrollmentId)
                    ->first();
                $grade ??= new Grade;
                $score = $entry['score'] === '' ? null : $entry['score'];
                $grade->forceFill([
                    'evaluation_id' => $evaluation->id,
                    'enrollment_id' => (int) $enrollmentId,
                    'classroom_id' => $evaluation->classroom_id,
                    'entered_by' => $actor->id,
                    'score' => $score,
                    'comment' => $entry['comment'] ?? null,
                ])->save();
            }
        });
    }

    public function publishEvaluation(Evaluation $evaluation, User $actor): Evaluation
    {
        $gradeIds = [];

        $publishedEvaluation = DB::transaction(function () use ($evaluation, $actor, &$gradeIds): Evaluation {
            $evaluation = Evaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if (! $actor->isAdmin()
                && (! $actor->isTeacher() || $actor->teacher === null || $actor->teacher->id !== $evaluation->teacher_id)) {
                throw new DomainException('Seul l’enseignant propriétaire ou l’administration peut publier cette évaluation.');
            }

            if ($evaluation->published_at !== null) {
                throw new DomainException('Cette évaluation est déjà publiée.');
            }

            $evaluation->forceFill([
                'published_at' => now(),
                'published_by' => $actor->id,
            ])->save();

            $gradeIds = $evaluation->grades()
                ->whereNotNull('score')
                ->pluck('id')
                ->all();

            return $evaluation->refresh();
        });

        if ($gradeIds !== []) {
            Grade::query()->whereKey($gradeIds)->get()
                ->each(fn (Grade $grade) => GradeRecorded::dispatch($grade));
        }

        return $publishedEvaluation;
    }

    public function correctPublishedGrade(Grade $grade, int|float|string $score, string $reason, User $actor): GradeCorrection
    {
        if (! $actor->isAdmin()) {
            throw new DomainException('Seule l’administration peut corriger une note publiée.');
        }

        if (! is_numeric($score)
            || (float) $score < 0
            || (float) $score > 20
            || mb_strlen(trim($reason)) < 8
            || mb_strlen(trim($reason)) > 2000) {
            throw new DomainException('La note doit être comprise entre 0 et 20 et le motif doit comporter de 8 à 2 000 caractères.');
        }

        return DB::transaction(function () use ($grade, $score, $reason, $actor): GradeCorrection {
            $evaluationId = Grade::query()->whereKey($grade->id)->value('evaluation_id');
            $evaluation = Evaluation::query()->whereKey($evaluationId)->lockForUpdate()->firstOrFail();
            $lockedGrade = Grade::query()->whereKey($grade->id)->lockForUpdate()->firstOrFail();

            if ($evaluation->published_at === null) {
                throw new DomainException('Seules les notes publiées peuvent être corrigées avec cette action.');
            }

            $newScore = number_format((float) $score, 2, '.', '');
            $previousScore = $lockedGrade->score === null
                ? null
                : number_format((float) $lockedGrade->score, 2, '.', '');

            if ($previousScore === $newScore) {
                throw new DomainException('La nouvelle note est identique à la note publiée.');
            }

            $lockedGrade->forceFill(['score' => $newScore])->save();

            return GradeCorrection::query()->create([
                'grade_id' => $lockedGrade->id,
                'corrected_by' => $actor->id,
                'previous_score' => $previousScore,
                'new_score' => $newScore,
                'reason' => $reason,
            ]);
        });
    }

    /** @return EloquentCollection<int, Enrollment> */
    public function roster(Evaluation $evaluation): EloquentCollection
    {
        return $this->rosterQuery($evaluation)
            ->with('student')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, array{rank: int, student: Student, average: float, subjects: array<string, float>}>
     */
    public function ranking(Classroom $classroom, SchoolYear $schoolYear): Collection
    {
        return $this->rankingBetween(
            $classroom,
            CarbonImmutable::parse($schoolYear->start_date->toDateString()),
            CarbonImmutable::parse($schoolYear->end_date->toDateString()),
        );
    }

    /**
     * @return Collection<int, array{rank: int, student: Student, average: float, subjects: array<string, float>}>
     */
    public function rankingBetween(
        Classroom $classroom,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        bool $publishedOnly = false,
    ): Collection {
        $grades = Grade::query()
            ->where('classroom_id', $classroom->id)
            ->whereNotNull('score')
            ->whereHas('evaluation', fn ($query) => $query
                ->whereBetween('evaluation_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->when($publishedOnly, fn ($published) => $published->whereNotNull('published_at')))
            ->with(['enrollment.student', 'evaluation.assignment.subject'])
            ->get();

        $ranked = $this->rankGrades($grades);
        $previousAverage = null;
        $rank = 0;

        foreach ($ranked as $index => &$row) {
            if ($row['average'] !== $previousAverage) {
                $rank = $index + 1;
                $previousAverage = $row['average'];
            }
            $row['rank'] = $rank;
        }
        unset($row);

        return collect($ranked);
    }

    /**
     * @param  Collection<int, Grade>  $grades
     * @return array{average: float|null, subjects: array<string, float>}
     */
    public function averagesForGrades(Collection $grades): array
    {
        if ($grades->isEmpty()) {
            return ['average' => null, 'subjects' => []];
        }

        return $this->calculateAverageBySubject($grades);
    }

    /**
     * @return Collection<int, array{student: Student, classroom: Classroom, school_year: SchoolYear, average: float, subjects: array<string, float>}>
     */
    public function summariesForStudent(Student $student): Collection
    {
        return $this->summariesForStudents(collect([$student]));
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array{student: Student, classroom: Classroom, school_year: SchoolYear, average: float, subjects: array<string, float>}>
     */
    public function summariesForStudents(Collection $students): Collection
    {
        if ($students->isEmpty()) {
            return collect();
        }

        $grades = Grade::query()
            ->whereNotNull('score')
            ->whereHas('evaluation', fn ($query) => $query->whereNotNull('published_at'))
            ->whereHas('enrollment', fn ($query) => $query->whereIn(
                'student_id',
                $students->map(fn (Student $student) => $student->getKey())->all(),
            ))
            ->with(['enrollment.student', 'evaluation.classroom.schoolYear', 'evaluation.assignment.subject'])
            ->get();

        return $grades
            ->groupBy(fn (Grade $grade) => $grade->enrollment->student_id)
            ->flatMap(function (Collection $studentGrades): Collection {
                return $studentGrades
                    ->groupBy(fn (Grade $grade) => $grade->evaluation->classroom_id)
                    ->map(function (Collection $classGrades) use ($studentGrades): array {
                        $evaluation = $classGrades->first()->evaluation;
                        $averages = $this->calculateAverageBySubject($classGrades);

                        return [
                            'student' => $studentGrades->first()->enrollment->student,
                            'classroom' => $evaluation->classroom,
                            'school_year' => $evaluation->classroom->schoolYear,
                            'average' => $averages['average'],
                            'subjects' => $averages['subjects'],
                        ];
                    });
            })
            ->values();
    }

    /**
     * @param  Collection<int, Grade>  $grades
     * @return array<int, array{rank: int, student: Student, average: float, subjects: array<string, float>}>
     */
    private function rankGrades(Collection $grades): array
    {
        return $grades
            ->groupBy(fn (Grade $grade) => $grade->enrollment->student_id)
            ->map(function (Collection $studentGrades): array {
                $averages = $this->calculateAverageBySubject($studentGrades);

                return [
                    'rank' => 0,
                    'student' => $studentGrades->first()->enrollment->student,
                    'average' => $averages['average'],
                    'subjects' => $averages['subjects'],
                ];
            })
            ->sort(function (array $left, array $right): int {
                return ($right['average'] <=> $left['average'])
                    ?: strcmp($left['student']->last_name, $right['student']->last_name)
                    ?: strcmp($left['student']->first_name, $right['student']->first_name);
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Grade>  $grades
     * @return array{average: float, subjects: array<string, float>}
     */
    private function calculateAverageBySubject(Collection $grades): array
    {
        $subjects = $grades
            ->groupBy(fn (Grade $grade) => $grade->evaluation->teaching_assignment_id)
            ->map(function (Collection $subjectGrades): array {
                $weightTotal = 0;
                $scoreTotal = 0.0;

                foreach ($subjectGrades as $grade) {
                    $weight = $grade->evaluation->coefficient;
                    $weightTotal += $weight;
                    $scoreTotal += (float) $grade->score * $weight;
                }

                $firstEvaluation = $subjectGrades->first()->evaluation;

                return [
                    'name' => $firstEvaluation->assignment->subject->name,
                    'average' => round($scoreTotal / $weightTotal, 2),
                    'coefficient' => $firstEvaluation->subject_coefficient,
                ];
            });

        $subjectWeightTotal = $subjects->sum('coefficient');
        $average = $subjects->sum(fn (array $subject) => $subject['average'] * $subject['coefficient']) / $subjectWeightTotal;

        return [
            'average' => round($average, 2),
            'subjects' => $subjects->mapWithKeys(fn (array $subject) => [$subject['name'] => $subject['average']])->all(),
        ];
    }

    private function rosterQuery(Evaluation $evaluation): Builder
    {
        return Enrollment::query()
            ->where('classroom_id', $evaluation->classroom_id)
            ->whereDate('enrolled_at', '<=', $evaluation->evaluation_date->toDateString())
            ->where(function ($query) use ($evaluation): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $evaluation->evaluation_date->toDateString());
            });
    }
}
