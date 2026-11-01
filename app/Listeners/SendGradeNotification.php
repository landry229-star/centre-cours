<?php

namespace App\Listeners;

use App\Enums\NotificationCategory;
use App\Events\GradeRecorded;
use App\Notifications\SchoolActivityNotifier;

class SendGradeNotification
{
    public function __construct(private readonly SchoolActivityNotifier $notifier) {}

    public function handle(GradeRecorded $event): void
    {
        $grade = $event->grade->loadMissing([
            'enrollment.student.guardian.user',
            'enrollment.student.user',
            'evaluation.assignment.subject',
        ]);
        $student = $grade->enrollment->student;
        $subject = $grade->evaluation->assignment->subject->name;
        $message = "Nouvelle note pour {$student->full_name} en {$subject} : ".number_format((float) $grade->score, 2, ',', ' ').' / 20.';
        $recipients = collect([$student->guardian?->user, $student->user])
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $url = $recipient->isGuardian()
                ? route('guardian.grades.index')
                : route('student.grades.index');

            $this->notifier->send($recipient, NotificationCategory::Grades, 'Nouvelle note', $message, $url);
        }
    }
}
