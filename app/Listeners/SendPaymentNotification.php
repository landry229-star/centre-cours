<?php

namespace App\Listeners;

use App\Enums\NotificationCategory;
use App\Events\PaymentRecorded;
use App\Notifications\SchoolActivityNotifier;
use App\Support\Format;

class SendPaymentNotification
{
    public function __construct(private readonly SchoolActivityNotifier $notifier) {}

    public function handle(PaymentRecorded $event): void
    {
        $payment = $event->payment->loadMissing('enrollment.student.guardian.user');
        $student = $payment->enrollment->student;
        $recipient = $student->guardian?->user;

        if ($recipient === null) {
            return;
        }

        $this->notifier->send(
            $recipient,
            NotificationCategory::Payment,
            'Paiement enregistré',
            "{$student->full_name} : paiement de ".Format::fcfa($payment->amount).' reçu pour '.Format::month($payment->month).'.',
            route('payments.receipt', $payment),
        );
    }
}
