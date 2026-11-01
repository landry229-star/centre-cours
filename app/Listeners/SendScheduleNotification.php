<?php

namespace App\Listeners;

use App\Enums\NotificationCategory;
use App\Events\ScheduleChanged;
use App\Notifications\SchoolActivityNotifier;

class SendScheduleNotification
{
    public function __construct(private readonly SchoolActivityNotifier $notifier) {}

    public function handle(ScheduleChanged $event): void
    {
        $schedule = $event->schedule->loadMissing([
            'classroom.schoolYear',
            'assignment.subject',
            'assignment.teacher.user',
            'room',
        ]);

        if (! $schedule->classroom->schoolYear->is_active) {
            return;
        }

        [$title, $verb] = match ($event->change) {
            'created' => ['Nouvelle séance ajoutée', 'ajoutée'],
            'updated' => ['Emploi du temps modifié', 'modifiée'],
            'deleted' => ['Séance retirée', 'retirée'],
            default => throw new \InvalidArgumentException("Type de changement d'emploi du temps inconnu : {$event->change}"),
        };
        $message = "{$schedule->assignment->subject->name} en {$schedule->classroom->name}, {$schedule->day_of_week->label()} de {$schedule->startTime()} à {$schedule->endTime()} en salle {$schedule->room->name} : séance {$verb}.";
        $recipients = collect([$schedule->assignment->teacher->user]);

        $enrollments = $schedule->classroom->enrollments()
            ->active()
            ->with(['student.guardian.user', 'student.user'])
            ->get();

        foreach ($enrollments as $enrollment) {
            $recipients->push($enrollment->student?->guardian?->user, $enrollment->student?->user);
        }

        foreach ($recipients->filter()->unique('id') as $recipient) {
            $url = match (true) {
                $recipient->isGuardian() => route('guardian.schedules.index'),
                $recipient->isStudent() => route('student.schedules.index'),
                $recipient->isTeacher() => route('teacher.schedules.index'),
                default => route('dashboard'),
            };

            $this->notifier->send($recipient, NotificationCategory::Schedule, $title, $message, $url);
        }
    }
}
