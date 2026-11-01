<?php

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;

/** Une ligne de l'échéancier : Élève | Mois | Attendu | Payé | Reste | Statut. */
final class MonthlyFee
{
    public readonly int $remaining;

    public readonly PaymentStatus $status;

    public function __construct(
        public readonly CarbonInterface $month,   // 1er jour du mois
        public readonly int $expected,            // montant attendu (FCFA)
        public readonly int $paid,                // somme des paiements valides
        public readonly ?CarbonInterface $dueDate,
        public readonly bool $due,
        public readonly bool $overdue,
        public readonly int $enrollmentId,        // inscription (classe) en vigueur ce mois-là
    ) {
        $this->remaining = max($expected - $paid, 0);
        $this->status = PaymentStatus::fromAmounts($expected, $paid);
    }

    /** Une mensualité est en impayé à partir du lendemain de sa date limite. */
    public function isArrear(): bool
    {
        return $this->overdue && $this->remaining > 0;
    }
}
