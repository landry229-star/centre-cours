<?php

use App\Enums\Cycle;
use App\Enums\EvaluationType;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Evaluation;
use App\Models\Level;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Performance\PerformanceAcademicDatasetProvisioner;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Command\Command;

uses(RefreshDatabase::class);

it('refuses to complete performance academics outside the local environment', function () {
    $this->artisan('performance:complete-academics', [
        '--confirm-database' => 'centre_cours',
    ])
        ->assertExitCode(Command::FAILURE)
        ->expectsOutput('Cette commande est réservée à l’environnement local.');
});

it('assigns primary-school subjects to performance classes and creates teacher profiles without login accounts', function () {
    $schoolYear = SchoolYear::factory()->active()->create();
    $level = Level::factory()->create([
        'name' => 'CI',
        'cycle' => Cycle::Primary,
        'position' => 1,
    ]);
    $classroom = Classroom::factory()->create([
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'name' => 'PERF26-001',
    ]);
    $mathSubject = Subject::factory()->create([
        'name' => 'Mathématiques',
        'code' => 'MATHDEMO',
    ]);

    $counts = app(PerformanceAcademicDatasetProvisioner::class)->populate();

    expect($counts['teachers'])->toBe(65)
        ->and($counts['subjects'])->toBe(14)
        ->and($counts['classes_with_curriculum'])->toBe(1)
        ->and($counts['teaching_assignments'])->toBe(6);

    expect(Teacher::query()->where('matricule', 'like', 'PERF26-ENS-%')->count())->toBe(65)
        ->and(Teacher::query()->where('matricule', 'like', 'PERF26-ENS-%')->whereNotNull('user_id')->count())->toBe(0)
        ->and(Subject::query()->whereHas('assignments', fn ($query) => $query->where('classroom_id', $classroom->id))->count())->toBe(6);

    Teacher::query()->where('matricule', 'like', 'PERF26-ENS-%')->each(function (Teacher $teacher): void {
        $number = (int) mb_substr($teacher->matricule, -3);
        $faker = FakerFactory::create('fr_FR');
        $faker->seed($number);
        $expectedGender = $faker->randomElement(['M', 'F']);
        $expectedFirstName = $expectedGender === 'M' ? $faker->firstNameMale() : $faker->firstNameFemale();

        expect($teacher->gender->value)->toBe($expectedGender)
            ->and($teacher->first_name)->toBe($expectedFirstName);
    });
    $this->assertModelExists($mathSubject);
    expect(Subject::query()->where('name', 'Mathématiques')->count())->toBe(1);
});

it('reuses existing performance teacher profiles without duplicating assignments', function () {
    $schoolYear = SchoolYear::factory()->active()->create();
    $level = Level::factory()->create([
        'name' => 'CI',
        'cycle' => Cycle::Primary,
        'position' => 1,
    ]);
    $classroom = Classroom::factory()->create([
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'name' => 'PERF26-001',
    ]);
    app(PerformanceAcademicDatasetProvisioner::class)->populate();
    $teacher = Teacher::query()->where('matricule', 'PERF26-ENS-001')->firstOrFail();
    $teacher->forceFill([
        'first_name' => 'Joseph',
        'gender' => $teacher->gender->value === 'F' ? 'M' : 'F',
    ])->save();

    $counts = app(PerformanceAcademicDatasetProvisioner::class)->populate();

    $faker = FakerFactory::create('fr_FR');
    $faker->seed(1);
    $expectedGender = $faker->randomElement(['M', 'F']);
    $expectedFirstName = $expectedGender === 'M' ? $faker->firstNameMale() : $faker->firstNameFemale();

    expect($counts['teachers'])->toBe(65)
        ->and(Teacher::query()->where('matricule', 'like', 'PERF26-ENS-%')->count())->toBe(65)
        ->and(TeachingAssignment::query()->where('classroom_id', $classroom->id)->count())->toBe(6)
        ->and($teacher->fresh()->gender->value)->toBe($expectedGender)
        ->and($teacher->fresh()->first_name)->toBe($expectedFirstName);
});

it('moves legacy performance evaluations to curriculum assignments before removing placeholders', function () {
    $schoolYear = SchoolYear::factory()->active()->create();
    $level = Level::factory()->create([
        'name' => 'CI',
        'cycle' => Cycle::Primary,
        'position' => 1,
    ]);
    $classroom = Classroom::factory()->create([
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'name' => 'PERF26-001',
    ]);
    $mathSubject = Subject::factory()->create([
        'name' => 'Performance - Mathématiques',
        'code' => 'PF26MATH',
    ]);
    $historySubject = Subject::factory()->create([
        'name' => 'Performance - Histoire',
        'code' => 'PF26HIST',
    ]);
    $scienceSubject = Subject::factory()->create([
        'name' => 'Sciences de la Vie et de la Terre',
        'code' => 'PF26SVT',
    ]);
    Subject::factory()->create([
        'name' => 'Mathématiques',
        'code' => 'MATHDEMO',
    ]);
    $legacyTeacher = Teacher::factory()->create();
    $legacyMathAssignment = $classroom->assignments()->create([
        'subject_id' => $mathSubject->id,
        'teacher_id' => $legacyTeacher->id,
        'coefficient' => 1,
    ]);
    $legacyHistoryAssignment = $classroom->assignments()->create([
        'subject_id' => $historySubject->id,
        'teacher_id' => $legacyTeacher->id,
        'coefficient' => 1,
    ]);
    $legacyScienceAssignment = $classroom->assignments()->create([
        'subject_id' => $scienceSubject->id,
        'teacher_id' => $legacyTeacher->id,
        'coefficient' => 1,
    ]);
    $legacySession = AttendanceSession::factory()->forAssignment($legacyMathAssignment)->create([
        'session_date' => $schoolYear->start_date,
        'starts_at' => '08:00:00',
        'ends_at' => '08:30:00',
    ]);
    $admin = User::factory()->admin()->create();
    $evaluation = new Evaluation;
    $evaluation->forceFill([
        'classroom_id' => $classroom->id,
        'teaching_assignment_id' => $legacyMathAssignment->id,
        'teacher_id' => $legacyTeacher->id,
        'created_by' => $admin->id,
        'title' => 'Ancienne évaluation de mathématiques',
        'type' => EvaluationType::Quiz,
        'evaluation_date' => $schoolYear->start_date,
        'coefficient' => 1,
        'subject_coefficient' => 1,
    ])->save();
    $historyEvaluation = new Evaluation;
    $historyEvaluation->forceFill([
        'classroom_id' => $classroom->id,
        'teaching_assignment_id' => $legacyHistoryAssignment->id,
        'teacher_id' => $legacyTeacher->id,
        'created_by' => $admin->id,
        'title' => 'Ancienne évaluation d’histoire',
        'type' => EvaluationType::Quiz,
        'evaluation_date' => $schoolYear->start_date,
        'coefficient' => 1,
        'subject_coefficient' => 1,
    ])->save();
    $scienceEvaluation = new Evaluation;
    $scienceEvaluation->forceFill([
        'classroom_id' => $classroom->id,
        'teaching_assignment_id' => $legacyScienceAssignment->id,
        'teacher_id' => $legacyTeacher->id,
        'created_by' => $admin->id,
        'title' => 'Ancienne évaluation de sciences',
        'type' => EvaluationType::Quiz,
        'evaluation_date' => $schoolYear->start_date,
        'coefficient' => 1,
        'subject_coefficient' => 1,
    ])->save();

    app(PerformanceAcademicDatasetProvisioner::class)->populate();

    $this->assertModelMissing($legacyMathAssignment);
    $this->assertModelMissing($legacyHistoryAssignment);
    $this->assertModelMissing($legacyScienceAssignment);
    $this->assertModelExists($evaluation->fresh());
    $this->assertModelExists($historyEvaluation->fresh());
    $this->assertModelExists($scienceEvaluation->fresh());
    $this->assertModelExists($legacySession->fresh());
    expect($evaluation->fresh()->assignment->subject->name)->toBe('Mathématiques')
        ->and($historyEvaluation->fresh()->assignment->subject->name)->toBe('Éducation Sociale')
        ->and($scienceEvaluation->fresh()->assignment->subject->name)->toBe('Éducation Scientifique et Technologique')
        ->and($legacySession->fresh()->subject->name)->toBe('Mathématiques');
});
