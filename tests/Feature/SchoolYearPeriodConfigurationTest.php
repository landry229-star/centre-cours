<?php

use App\Models\Classroom;
use App\Models\Evaluation;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Grades\GradebookService;
use App\Services\Grades\SchoolYearPeriodService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates dated trimester, semester, and annual defaults covering the full school year', function () {
    $schoolYear = SchoolYear::factory()->active()->create([
        'start_date' => '2030-08-01',
        'end_date' => '2031-05-31',
    ]);

    $periods = app(SchoolYearPeriodService::class)->periods($schoolYear);

    expect($periods)->toHaveCount(6)
        ->and($periods[0]['start']->toDateString())->toBe('2030-08-01')
        ->and($periods[2]['end']->toDateString())->toBe('2031-05-31')
        ->and($periods[5]['label'])->toBe('Année complète')
        ->and($periods[5]['start']->toDateString())->toBe('2030-08-01')
        ->and($periods[5]['end']->toDateString())->toBe('2031-05-31');
});

it('saves custom period dates and locks them once an evaluation exists', function () {
    $schoolYear = SchoolYear::factory()->active()->create([
        'start_date' => '2030-08-01',
        'end_date' => '2031-05-31',
    ]);
    $periods = [
        'trimester' => [
            1 => ['start_date' => '2030-08-01', 'end_date' => '2030-10-31'],
            2 => ['start_date' => '2030-11-01', 'end_date' => '2031-01-31'],
            3 => ['start_date' => '2031-02-01', 'end_date' => '2031-05-31'],
        ],
        'semester' => [
            1 => ['start_date' => '2030-08-01', 'end_date' => '2030-12-31'],
            2 => ['start_date' => '2031-01-01', 'end_date' => '2031-05-31'],
        ],
        'annual' => [
            1 => ['start_date' => '2030-08-01', 'end_date' => '2031-05-31'],
        ],
    ];
    $periodService = app(SchoolYearPeriodService::class);
    $periodService->save($schoolYear, $periods);
    $storedTrimester = collect($periodService->periods($schoolYear))
        ->first(fn (array $period): bool => $period['type'] === 'trimester' && $period['number'] === 1);

    expect($storedTrimester['end']->toDateString())->toBe('2030-10-31');

    $classroom = Classroom::factory()->create(['school_year_id' => $schoolYear->id]);
    $assignment = TeachingAssignment::factory()->create([
        'classroom_id' => $classroom->id,
        'subject_id' => Subject::factory()->create()->id,
        'teacher_id' => Teacher::factory()->create()->id,
    ]);
    app(GradebookService::class)->createEvaluation($assignment, [
        'title' => 'Évaluation verrouillante',
        'type' => 'quiz',
        'evaluation_date' => '2030-10-15',
        'coefficient' => 1,
    ], User::factory()->admin()->create());

    expect($periodService->isLocked($schoolYear))->toBeTrue()
        ->and(fn () => $periodService->save($schoolYear, $periods))->toThrow(DomainException::class);
    expect(Evaluation::query()->count())->toBe(1)
        ->and(collect($periodService->periods($schoolYear))
            ->first(fn (array $period): bool => $period['type'] === 'trimester' && $period['number'] === 1)['end']->toDateString())
        ->toBe('2030-10-31');
});

it('rejects periods that leave gaps or overlap', function () {
    $schoolYear = SchoolYear::factory()->create([
        'start_date' => '2030-08-01',
        'end_date' => '2031-05-31',
    ]);
    $periods = [
        'trimester' => [
            1 => ['start_date' => '2030-08-01', 'end_date' => '2030-10-31'],
            2 => ['start_date' => '2030-11-02', 'end_date' => '2031-01-31'],
            3 => ['start_date' => '2031-02-01', 'end_date' => '2031-05-31'],
        ],
        'semester' => [
            1 => ['start_date' => '2030-08-01', 'end_date' => '2030-12-31'],
            2 => ['start_date' => '2031-01-01', 'end_date' => '2031-05-31'],
        ],
        'annual' => [
            1 => ['start_date' => '2030-08-01', 'end_date' => '2031-05-31'],
        ],
    ];

    expect(fn () => app(SchoolYearPeriodService::class)->save($schoolYear, $periods))
        ->toThrow(DomainException::class);
});
