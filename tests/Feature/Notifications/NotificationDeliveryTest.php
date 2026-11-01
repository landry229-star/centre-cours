<?php

use App\Enums\AttendanceStatus;
use App\Enums\NotificationCategory;
use App\Enums\PaymentMethod;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Room;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\SchoolActivityNotification;
use App\Notifications\SchoolActivityNotifier;
use App\Services\Academics\ScheduleService;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\PaymentService;
use App\Services\Grades\GradebookService;
use App\Support\Format;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAttendance;
use Tests\Concerns\CreatesBilling;

uses(RefreshDatabase::class, CreatesAttendance::class, CreatesBilling::class);

it('notifies a guardian after a payment is recorded', function () {
    $this->goToJanuary2031();
    $guardianUser = User::factory()->guardian()->create();
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $enrollment = $this->billingEnrollment(guardian: $guardian);
    $cashier = User::factory()->admin()->create();

    $payment = app(PaymentService::class)->record(
        $enrollment,
        now()->subMonths(2),
        5000,
        PaymentMethod::Cash,
        now(),
        null,
        null,
        $cashier,
    );

    expect($guardianUser->notifications()->count())->toBe(1);
    $notification = $guardianUser->notifications()->firstOrFail();
    expect($notification->data)->toMatchArray([
        'category' => 'payment',
        'title' => 'Paiement enregistré',
        'url' => route('payments.receipt', $payment),
    ]);
    expect($notification->data['message'])->toContain(Format::fcfa(5000));
});

it('respects optional notification preferences and always delivers private message notifications', function () {
    $user = User::factory()->guardian()->create();
    $preferences = collect(NotificationCategory::preferenceCategories())
        ->mapWithKeys(fn (NotificationCategory $category): array => [$category->value => false])
        ->all();
    $user->forceFill(['notification_preferences' => $preferences])->save();
    $notifier = app(SchoolActivityNotifier::class);

    foreach (NotificationCategory::preferenceCategories() as $category) {
        $notifier->send($user, $category, $category->label(), 'Message de test.', route('dashboard'));
    }

    expect($user->notifications()->count())->toBe(0);

    $notifier->send($user, NotificationCategory::Message, 'Message privé', 'Message obligatoire.', route('dashboard'));

    expect($user->notifications()->count())->toBe(1);
    expect($user->notifications()->firstOrFail()->data['category'])->toBe('message');
});

it('notifies linked parents and students only when a new absence is recorded', function () {
    $setup = $this->attendanceSetup(1);
    $enrollment = $setup['enrollments']->first();
    $guardianUser = User::factory()->guardian()->create();
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $studentUser = User::factory()->student()->create();
    $enrollment->student->forceFill([
        'guardian_id' => $guardian->id,
        'user_id' => $studentUser->id,
    ])->save();
    $session = AttendanceSession::factory()->forAssignment($setup['assignment'])->create();

    app(AttendanceService::class)->record(
        $session,
        [$enrollment->id => ['status' => AttendanceStatus::Absent->value]],
        $setup['user'],
    );

    expect($guardianUser->notifications()->count())->toBe(1);
    expect($studentUser->notifications()->count())->toBe(1);
    expect($guardianUser->notifications()->firstOrFail()->data)->toMatchArray([
        'category' => 'attendance',
        'url' => route('guardian.attendance.index'),
    ]);

    app(AttendanceService::class)->record(
        $session,
        [$enrollment->id => ['status' => AttendanceStatus::Absent->value]],
        $setup['user'],
    );

    expect($guardianUser->notifications()->count())->toBe(1);
    expect($studentUser->notifications()->count())->toBe(1);
});

it('notifies linked parents and students only when grades are published', function () {
    $year = SchoolYear::factory()->active()->create([
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(8)->toDateString(),
    ]);
    $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
    $teacher = Teacher::factory()->create();
    $assignment = $classroom->assignments()->create([
        'subject_id' => Subject::factory()->create()->id,
        'teacher_id' => $teacher->id,
        'coefficient' => 1,
    ]);
    $guardianUser = User::factory()->guardian()->create();
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $studentUser = User::factory()->student()->create();
    $student = Student::factory()->create([
        'guardian_id' => $guardian->id,
        'user_id' => $studentUser->id,
    ]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $year->id,
        'enrolled_at' => now()->subDays(3)->toDateString(),
    ]);
    $evaluation = app(GradebookService::class)->createEvaluation($assignment, [
        'title' => 'Interrogation',
        'type' => 'quiz',
        'evaluation_date' => now()->toDateString(),
        'coefficient' => 1,
    ], User::factory()->admin()->create());
    $service = app(GradebookService::class);
    $admin = User::factory()->admin()->create();

    $service->recordGrades($evaluation, [$enrollment->id => ['score' => 15]], $admin);

    expect($guardianUser->notifications()->count())->toBe(0)
        ->and($studentUser->notifications()->count())->toBe(0);

    $service->recordGrades($evaluation, [$enrollment->id => ['score' => '15.00', 'comment' => 'Corrigé']], $admin);
    $service->recordGrades($evaluation, [$enrollment->id => ['score' => null]], $admin);
    $service->recordGrades($evaluation, [$enrollment->id => ['score' => 0]], $admin);

    expect($guardianUser->notifications()->count())->toBe(0)
        ->and($studentUser->notifications()->count())->toBe(0);

    $service->publishEvaluation($evaluation, $admin);

    expect($guardianUser->notifications()->count())->toBe(1)
        ->and($studentUser->notifications()->count())->toBe(1);
    expect($guardianUser->notifications()->firstOrFail()->data)->toMatchArray([
        'category' => 'grades',
        'message' => 'Nouvelle note pour '.$student->full_name.' en '.$assignment->subject->name.' : 0,00 / 20.',
    ]);
});

it('notifies schedule recipients on actual changes and uses role-specific destinations', function () {
    $year = SchoolYear::factory()->active()->create();
    $classroom = Classroom::factory()->create(['school_year_id' => $year->id]);
    $teacherUser = User::factory()->teacher()->create();
    $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
    $assignment = $classroom->assignments()->create([
        'subject_id' => Subject::factory()->create()->id,
        'teacher_id' => $teacher->id,
        'coefficient' => 1,
    ]);
    $guardianUser = User::factory()->guardian()->create();
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $studentUser = User::factory()->student()->create();
    $student = Student::factory()->create([
        'guardian_id' => $guardian->id,
        'user_id' => $studentUser->id,
    ]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $year->id,
        'enrolled_at' => now()->subDays(3)->toDateString(),
    ]);
    $admin = User::factory()->admin()->create();
    $room = Room::factory()->create();
    $service = app(ScheduleService::class);
    $payload = [
        'assignment_id' => $assignment->id,
        'room_id' => $room->id,
        'day_of_week' => 'monday',
        'starts_at' => '08:00',
        'ends_at' => '09:00',
    ];

    $schedule = $service->create($payload, $admin);

    expect($teacherUser->notifications()->count())->toBe(1);
    expect($guardianUser->notifications()->count())->toBe(1);
    expect($studentUser->notifications()->count())->toBe(1);
    expect($guardianUser->notifications()->firstOrFail()->data['url'])->toBe(route('guardian.schedules.index'));
    expect($studentUser->notifications()->firstOrFail()->data['url'])->toBe(route('student.schedules.index'));
    expect($teacherUser->notifications()->firstOrFail()->data['url'])->toBe(route('teacher.schedules.index'));
    expect($teacherUser->notifications()->firstOrFail()->data['category'])->toBe('schedule');
    expect($teacherUser->notifications()->firstOrFail()->data['message'])->toContain($room->name);

    expect(fn () => $service->create($payload, $admin))->toThrow(DomainException::class);
    expect($teacherUser->notifications()->count())->toBe(1);

    $updatedPayload = array_merge($payload, ['starts_at' => '10:00', 'ends_at' => '11:00']);
    $service->update($schedule, $updatedPayload, $admin);

    expect($teacherUser->notifications()->count())->toBe(2);
    $service->update($schedule->fresh(), $updatedPayload, $admin);
    expect($teacherUser->notifications()->count())->toBe(2);

    $service->delete($schedule->fresh());

    expect($teacherUser->notifications()->count())->toBe(3);
    expect($guardianUser->notifications()->count())->toBe(3);
    expect($studentUser->notifications()->count())->toBe(3);
});

it('shows only the authenticated users notifications and limits read actions to their own records', function () {
    $owner = User::factory()->guardian()->create();
    $other = User::factory()->guardian()->create();
    $owner->notify(new SchoolActivityNotification('grades', 'Note publiée', 'Une nouvelle note est disponible.', route('dashboard')));
    $other->notify(new SchoolActivityNotification('payment', 'Paiement reçu', 'Un paiement a été enregistré.', route('dashboard')));
    $ownerNotification = $owner->notifications()->firstOrFail();
    $otherNotification = $other->notifications()->firstOrFail();

    $this->get(route('notifications.index'))->assertRedirect(route('login'));

    $this->actingAs($owner)
        ->get(route('notifications.index'))
        ->assertSee('Note publiée')
        ->assertSee('Notes')
        ->assertDontSee('Paiement reçu')
        ->assertSee('1 notification non lue');

    $this->from(route('notifications.index'))
        ->actingAs($owner)
        ->patch(route('notifications.read', $otherNotification->id))
        ->assertNotFound();
    $this->assertNull($otherNotification->fresh()->read_at);

    $this->from(route('notifications.index'))
        ->actingAs($owner)
        ->patch(route('notifications.read', $ownerNotification->id))
        ->assertRedirect(route('notifications.index'));
    expect($ownerNotification->fresh()->read_at)->not->toBeNull();

    $owner->notify(new SchoolActivityNotification('grades', 'Deuxième note', 'Une autre note est disponible.', route('dashboard')));
    $this->from(route('notifications.index'))
        ->actingAs($owner)
        ->patch(route('notifications.read-all'))
        ->assertRedirect(route('notifications.index'));

    expect($owner->unreadNotifications()->count())->toBe(0);
    expect($other->unreadNotifications()->count())->toBe(1);
});
