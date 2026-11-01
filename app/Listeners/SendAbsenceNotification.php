<?php

namespace App\Listeners;

use App\Enums\NotificationCategory;
use App\Events\StudentMarkedAbsent;
use App\Notifications\SchoolActivityNotifier;
use App\Support\Format;

class SendAbsenceNotification
{
    public function __construct(private readonly SchoolActivityNotifier $notifier) {}

    public function handle(StudentMarkedAbsent $event): void
    {
        $attendance = $event->attendance->loadMissing([
            'enrollment.student.guardian.user',
            'enrollment.student.user',
            'session.classroom',
            'session.subject',
        ]);
        $student = $attendance->enrollment->student;
        $session = $attendance->session;
        $activity = $session->isDaily() ? 'appel quotidien' : $session->subject->name;
        $message = "{$student->full_name} est absent(e) le ".Format::date($session->session_date)." ({$activity}, {$session->classroom->name}).";
        $recipients = collect([$student->guardian?->user, $student->user])
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $url = $recipient->isGuardian()
                ? route('guardian.attendance.index')
                : route('student.dashboard');

            $this->notifier->send($recipient, NotificationCategory::Attendance, 'Absence signalée', $message, $url);
        }
    }
}
