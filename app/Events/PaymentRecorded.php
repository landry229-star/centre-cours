<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/** Émis après validation d'un paiement pour notifier le parent concerné. */
class PaymentRecorded
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
