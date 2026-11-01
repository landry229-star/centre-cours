<?php

use App\Enums\PaymentMethod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeeDueDate;
use App\Models\FeeSchedule;
use App\Models\Level;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;

it('shows monthly payments arrears payment methods and accurate monthly trends', function () {
    $this->travelTo('2026-10-04 12:00:00');

    $schoolYear = SchoolYear::factory()->active()->create([
        'label' => '2026-2027',
        'start_date' => '2026-08-01',
        'end_date' => '2027-05-31',
    ]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create([
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
    ]);
    $student = Student::factory()->create();
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'enrolled_at' => '2026-08-01',
    ]);
    $admin = User::factory()->admin()->create();
    Teacher::factory()->create();
    FeeSchedule::factory()->create([
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'monthly_amount' => 10000,
    ]);
    foreach (['2026-08-10', '2026-09-10', '2026-10-01'] as $dueDate) {
        FeeDueDate::factory()->create([
            'student_id' => $student->id,
            'school_year_id' => $schoolYear->id,
            'month' => Carbon::parse($dueDate)->startOfMonth()->toDateString(),
            'due_date' => $dueDate,
        ]);
    }
    Payment::factory()->create([
        'enrollment_id' => $enrollment->id,
        'month' => '2026-10-01',
        'paid_at' => '2026-10-03',
        'amount' => 5000,
        'method' => PaymentMethod::Cash,
        'recorded_by' => $admin->id,
    ]);
    Payment::factory()->cancelled()->create([
        'enrollment_id' => $enrollment->id,
        'month' => '2026-10-01',
        'paid_at' => '2026-10-03',
        'amount' => 9000,
        'recorded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['collected'] === 5000
            && $metrics['payments_this_month'] === 1
            && $metrics['arrears_amount'] === 25000
            && $metrics['arrears_students'] === 1)
        ->assertViewHas('paymentMethods', fn ($methods): bool => $methods
            ->firstWhere('method', PaymentMethod::Cash)['count'] === 1
            && $methods->firstWhere('method', PaymentMethod::Cash)['amount'] === 5000)
        ->assertViewHas('monthlyTrends', fn ($trends): bool => $trends->pluck('key')->all() === [
            '2026-05',
            '2026-06',
            '2026-07',
            '2026-08',
            '2026-09',
            '2026-10',
        ]
            && $trends->firstWhere('key', '2026-08')['enrollment_count'] === 1
            && $trends->firstWhere('key', '2026-10')['payment_amount'] === 5000)
        ->assertSee(route('admin.settings.centre.edit'), false)
        ->assertSeeText('Paramètres du centre')
        ->assertSeeText('Encaissé ce mois')
        ->assertSeeText('Paiements par moyen');
});
