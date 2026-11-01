<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Services\CentreSettingsService;
use App\Support\AmountInWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Génère le reçu à la demande. Rien n'est stocké sur le disque : le fichier est recréé
 * depuis la base à chaque téléchargement, donc il n'y a aucun document privé à protéger
 * (et le contrôle d'accès se fait uniquement dans PaymentPolicy).
 */
class ReceiptPdfService
{
    public function __construct(private CentreSettingsService $centreSettings) {}

    public function make(Payment $payment): DomPdf
    {
        $payment->load(['enrollment.student.guardian', 'enrollment.classroom.level', 'enrollment.schoolYear', 'recorder', 'canceller']);

        return Pdf::loadView('receipts.pdf', [
            'payment' => $payment,
            'student' => $payment->enrollment->student,
            'classroom' => $payment->enrollment->classroom,
            'year' => $payment->enrollment->schoolYear,
            'amountInWords' => AmountInWords::francs($payment->amount),
            'centre' => $this->centreSettings->current(),
            'logo' => $this->centreSettings->logoDataUri(),
        ])->setPaper('a5', 'landscape');
    }

    public function filename(Payment $payment): string
    {
        return 'recu-'.$payment->receipt_number.'.pdf';
    }
}
