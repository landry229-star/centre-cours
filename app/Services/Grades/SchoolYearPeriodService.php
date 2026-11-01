<?php

namespace App\Services\Grades;

use App\Models\Evaluation;
use App\Models\SchoolYear;
use App\Models\SchoolYearPeriod;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class SchoolYearPeriodService
{
    /** @var array<string, array{label: string, count: int}> */
    private const DEFINITIONS = [
        'trimester' => ['label' => 'Trimestre', 'count' => 3],
        'semester' => ['label' => 'Semestre', 'count' => 2],
        'annual' => ['label' => 'Année complète', 'count' => 1],
    ];

    /**
     * @return array<int, array{type: string, number: int, label: string, start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function periods(SchoolYear $schoolYear): array
    {
        $this->ensureDefaults($schoolYear);

        return SchoolYearPeriod::query()
            ->where('school_year_id', $schoolYear->id)
            ->orderByRaw("CASE period_type WHEN 'trimester' THEN 1 WHEN 'semester' THEN 2 ELSE 3 END")
            ->orderBy('period_number')
            ->get()
            ->map(fn (SchoolYearPeriod $period): array => [
                'type' => $period->period_type,
                'number' => (int) $period->period_number,
                'label' => self::DEFINITIONS[$period->period_type]['label']
                    .($period->period_type === 'annual' ? '' : ' '.$period->period_number),
                'start' => CarbonImmutable::parse($period->start_date->toDateString()),
                'end' => CarbonImmutable::parse($period->end_date->toDateString()),
            ])
            ->all();
    }

    public function ensureDefaults(SchoolYear $schoolYear): void
    {
        $count = SchoolYearPeriod::query()->where('school_year_id', $schoolYear->id)->count();

        if ($count === 6) {
            return;
        }

        DB::transaction(function () use ($schoolYear): void {
            $lockedSchoolYear = SchoolYear::query()->whereKey($schoolYear->id)->lockForUpdate()->firstOrFail();
            $existingCount = SchoolYearPeriod::query()
                ->where('school_year_id', $lockedSchoolYear->id)
                ->count();

            if ($existingCount === 6) {
                return;
            }

            if ($existingCount !== 0) {
                throw new DomainException('La configuration des périodes de cette année scolaire est incomplète.');
            }

            foreach (self::DEFINITIONS as $type => $definition) {
                foreach ($this->defaultRanges($lockedSchoolYear, $definition['count']) as $number => $range) {
                    SchoolYearPeriod::query()->create([
                        'school_year_id' => $lockedSchoolYear->id,
                        'period_type' => $type,
                        'period_number' => $number,
                        'start_date' => $range['start']->toDateString(),
                        'end_date' => $range['end']->toDateString(),
                    ]);
                }
            }
        });
    }

    public function isLocked(SchoolYear $schoolYear): bool
    {
        return Evaluation::query()
            ->whereHas('classroom', fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->exists();
    }

    /**
     * @param  array<string, array<int, array{start_date: string, end_date: string}>>  $periods
     */
    public function save(SchoolYear $schoolYear, array $periods): void
    {
        DB::transaction(function () use ($schoolYear, $periods): void {
            $lockedSchoolYear = SchoolYear::query()->whereKey($schoolYear->id)->lockForUpdate()->firstOrFail();
            $this->validateRanges($lockedSchoolYear, $periods);

            if ($this->isLocked($lockedSchoolYear)) {
                throw new DomainException('Les périodes sont verrouillées dès qu’une évaluation existe pour cette année scolaire.');
            }

            foreach (self::DEFINITIONS as $type => $definition) {
                for ($number = 1; $number <= $definition['count']; $number++) {
                    SchoolYearPeriod::query()->updateOrCreate(
                        [
                            'school_year_id' => $lockedSchoolYear->id,
                            'period_type' => $type,
                            'period_number' => $number,
                        ],
                        [
                            'start_date' => $periods[$type][$number]['start_date'],
                            'end_date' => $periods[$type][$number]['end_date'],
                        ],
                    );
                }
            }
        });
    }

    /**
     * @param  array<string, array<int, array{start_date: string, end_date: string}>>  $periods
     */
    private function validateRanges(SchoolYear $schoolYear, array $periods): void
    {
        foreach (self::DEFINITIONS as $type => $definition) {
            if (count($periods[$type] ?? []) !== $definition['count']) {
                throw new DomainException('Chaque période doit avoir des dates de début et de fin.');
            }

            $start = CarbonImmutable::parse($schoolYear->start_date->toDateString());
            $end = CarbonImmutable::parse($schoolYear->end_date->toDateString());

            for ($number = 1; $number <= $definition['count']; $number++) {
                $range = $periods[$type][$number] ?? null;

                if ($range === null) {
                    throw new DomainException('La numérotation des périodes est invalide.');
                }

                $periodStart = CarbonImmutable::parse($range['start_date']);
                $periodEnd = CarbonImmutable::parse($range['end_date']);

                if ($periodStart->toDateString() !== $start->toDateString()
                    || $periodEnd->lessThan($periodStart)
                    || $periodEnd->greaterThan($end)) {
                    throw new DomainException('Les périodes doivent se suivre sans chevauchement et couvrir toute l’année scolaire.');
                }

                $start = $periodEnd->addDay();
            }

            if ($start->toDateString() !== $end->addDay()->toDateString()) {
                throw new DomainException('Les périodes doivent couvrir toute l’année scolaire sans intervalle vide.');
            }
        }
    }

    /**
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function defaultRanges(SchoolYear $schoolYear, int $count): array
    {
        $start = CarbonImmutable::parse($schoolYear->start_date->toDateString());
        $days = $start->diffInDays(CarbonImmutable::parse($schoolYear->end_date->toDateString())) + 1;
        $ranges = [];

        for ($number = 1; $number <= $count; $number++) {
            $startOffset = intdiv($days * ($number - 1), $count);
            $endOffset = intdiv($days * $number, $count) - 1;

            $ranges[$number] = [
                'start' => $start->addDays($startOffset),
                'end' => $start->addDays($endOffset),
            ];
        }

        return $ranges;
    }
}
