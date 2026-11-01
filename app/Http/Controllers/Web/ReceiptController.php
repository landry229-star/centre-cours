<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\ReceiptPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reçu PDF. Route partagée (admin ET parent) : c'est PaymentPolicy::downloadReceipt qui décide.
 * Un parent qui change l'identifiant dans l'URL reçoit 403.
 */
class ReceiptController extends Controller
{
    public function __invoke(Request $request, Payment $payment, ReceiptPdfService $receipts): Response
    {
        Gate::authorize('downloadReceipt', $payment);

        $pdf = $receipts->make($payment);
        $filename = $receipts->filename($payment);

        // ?download=1 force l'enregistrement ; sinon le PDF s'ouvre dans le navigateur (impression).
        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
