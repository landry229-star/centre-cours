<?php

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('creates a complete and repeatable school demonstration dataset', function () {
    $this->travelTo('2026-10-05 12:00:00');

    $this->seed(DatabaseSeeder::class);

    expect(Classroom::count())->toBe(2)
        ->and(Teacher::count())->toBe(1)
        ->and(Guardian::count())->toBe(1)
        ->and(Student::count())->toBe(2)
        ->and(AttendanceSession::count())->toBe(2)
        ->and(Attendance::count())->toBe(2)
        ->and(Evaluation::count())->toBe(2)
        ->and(Grade::count())->toBe(2)
        ->and(Payment::count())->toBe(2)
        ->and(Schedule::count())->toBe(2)
        ->and(Announcement::count())->toBe(1);

    $announcement = Announcement::firstOrFail();
    expect($announcement->title)->toBe('Annonce fictive — classe de démonstration');
    expect($announcement->recipients()->pluck('users.email')->all())->toBe(['parent@centre.test']);

    $this->assertDatabaseHas('students', [
        'user_id' => User::query()->where('email', 'eleve@centre.test')->value('id'),
        'matricule' => 'ELV-DEMO-0001',
    ]);
    $this->assertDatabaseHas('guardians', [
        'user_id' => User::query()->where('email', 'parent@centre.test')->value('id'),
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(Classroom::count())->toBe(2)
        ->and(Teacher::count())->toBe(1)
        ->and(Guardian::count())->toBe(1)
        ->and(Student::count())->toBe(2)
        ->and(AttendanceSession::count())->toBe(2)
        ->and(Attendance::count())->toBe(2)
        ->and(Evaluation::count())->toBe(2)
        ->and(Grade::count())->toBe(2)
        ->and(Payment::count())->toBe(2)
        ->and(Schedule::count())->toBe(2)
        ->and(Announcement::count())->toBe(1);

    expect(User::query()->where('email', 'parent@centre.test')->firstOrFail()->notifications()->count())->toBe(1);
});
