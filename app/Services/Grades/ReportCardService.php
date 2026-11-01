<?php

namespace App\Services\Grades;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolYear;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;

class ReportCardService
{
    public function __construct(
        private GradebookService $gradebook,
        private SchoolYearPeriodService $periodConfiguration,
    ) {}

    /**
     * @return array<int, array{type: string, number: int, label: string, start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function periods(SchoolYear $schoolYear): array
    {
        return $this->periodConfiguration->periods($schoolYear);
    }

    /**
     * @return array{
     *     enrollment: Enrollment,
     *     period: array{type: string, number: int, label: string, start: CarbonImmutable, end: CarbonImmutable},
     *     grades: Collection<int, Grade>,
     *     subjects: Collection<int, array{name: string, average: float, coefficient: int, grades: Collection<int, Grade>}>,
     *     average: float|null,
     *     rank: int|null,
     *     class_size: int
     * }
     */
    public function make(Enrollment $enrollment, string $type, int $number, bool $publishedOnly = true): array
    {
        $enrollment->loadMissing(['student', 'classroom.level', 'schoolYear']);
        $period = $this->periodRange($enrollment->schoolYear, $type, $number);
        $grades = Grade::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereNotNull('score')
            ->whereHas('evaluation', fn ($query) => $query
                ->whereBetween('evaluation_date', [
                    $period['start']->toDateString(),
                    $period['end']->toDateString(),
                ])
                ->when($publishedOnly, fn ($published) => $published->whereNotNull('published_at')))
            ->with(['evaluation.assignment.subject', 'evaluation.teacher'])
            ->get()
            ->sortBy(fn (Grade $grade) => $grade->evaluation->evaluation_date)
            ->values();
        $averages = $this->gradebook->averagesForGrades($grades);
        $subjects = $grades
            ->groupBy(fn (Grade $grade) => $grade->evaluation->teaching_assignment_id)
            ->map(function (Collection $subjectGrades) use ($averages): array {
                $evaluation = $subjectGrades->first()->evaluation;

                return [
                    'name' => $evaluation->assignment->subject->name,
                    'average' => $averages['subjects'][$evaluation->assignment->subject->name],
                    'coefficient' => (int) $evaluation->subject_coefficient,
                    'grades' => $subjectGrades,
                ];
            })
            ->sortBy('name')
            ->values();
        $ranking = $this->gradebook->rankingBetween(
            $enrollment->classroom,
            $period['start'],
            $period['end'],
            $publishedOnly,
        );
        $studentRank = $ranking->first(
            fn (array $row): bool => (int) $row['student']->id === (int) $enrollment->student_id,
        );

        return [
            'enrollment' => $enrollment,
            'period' => $period,
            'grades' => $grades,
            'subjects' => $subjects,
            'average' => $averages['average'],
            'rank' => $studentRank['rank'] ?? null,
            'class_size' => $ranking->count(),
        ];
    }

    /**
     * @return array{type: string, number: int, label: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    private function periodRange(SchoolYear $schoolYear, string $type, int $number): array
    {
        $period = collect($this->periods($schoolYear))
            ->first(fn (array $period): bool => $period['type'] === $type && $period['number'] === $number);

        if ($period === null) {
            throw new DomainException('La période de bulletin demandée est invalide.');
        }

        return $period;
    }
}
