<?php

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Events\PaymentRecorded;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\User;
use App\Support\Format;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Toute écriture de paiement passe ici (formulaire web aujourd'hui, API REST en Phase 15).
 * Les contraintes PostgreSQL restent le dernier rempart.
 */
class PaymentService
{
    public function __construct(
        private readonly FeeStatementService $statements,
        private readonly ReceiptNumberGenerator $receipts,
    ) {}

    public function record(
        Enrollment $enrollment,
        CarbonInterface $month,
        int $amount,
        PaymentMethod $method,
        CarbonInterface $paidAt,
        ?string $reference,
        ?string $payerName,
        User $recorder,
    ): Payment {
        if ($amount <= 0) {
            throw new DomainException('Le montant doit être supérieur à 0.');
        }

        if ($paidAt->copy()->startOfDay()->gt(now()->startOfDay())) {
            throw new DomainException('La date de paiement ne peut pas être dans le futur.');
        }

        $month = $month->copy()->startOfMonth();
        $reference = $reference !== null && trim($reference) !== '' ? trim($reference) : null;
        $payerName = $payerName !== null && trim($payerName) !== '' ? trim($payerName) : null;

        try {
            $payment = DB::transaction(function () use ($enrollment, $month, $amount, $method, $paidAt, $reference, $payerName, $recorder) {
                // Verrou sur l'élève : deux caissiers ne peuvent pas encaisser en même temps le même mois
                // (sinon chacun verrait « reste 15 000 » et le mois serait payé deux fois).
                DB::table('students')->where('id', $enrollment->student_id)->lockForUpdate()->first();

                $year = SchoolYear::query()->whereKey($enrollment->school_year_id)->lockForUpdate()->firstOrFail();

                Classroom::withTrashed()
                    ->whereIn('id', Enrollment::query()
                        ->where('student_id', $enrollment->student_id)
                        ->where('school_year_id', $year->id)
                        ->select('classroom_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $statement = $this->statements->forStudent($year, $enrollment->student_id);

                if ($statement === null || $statement->error !== null) {
                    throw new DomainException($statement?->error ?? 'Aucun échéancier pour cet élève.');
                }

                $row = $statement->rows->first(fn (MonthlyFee $r) => $r->month->isSameDay($month));

                if ($row === null) {
                    throw new DomainException('Ce mois n\'est pas facturable pour cet élève (hors période d\'inscription).');
                }

                if ($row->remaining === 0) {
                    throw new DomainException('La mensualité de '.Format::month($month).' est déjà entièrement payée.');
                }

                if ($amount > $row->remaining) {
                    throw new DomainException('Le montant dépasse le reste à payer pour '.Format::month($month).' ('.Format::fcfa($row->remaining).').');
                }

                $payment = new Payment;
                $payment->forceFill([
                    // L'inscription en vigueur CE mois-là (la classe figurant sur le reçu).
                    'enrollment_id' => $row->enrollmentId,
                    'month' => $month->toDateString(),
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'payer_name' => $payerName ?? $statement->student->guardian?->full_name ?? 'Non renseigné',
                    'paid_at' => $paidAt->toDateString(),
                    'receipt_number' => $this->receipts->next(),
                    'recorded_by' => $recorder->id,
                ])->save();

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Cette référence de transaction est déjà utilisée par un autre paiement valide.');
        }

        PaymentRecorded::dispatch($payment);

        return $payment;
    }

    /** Annule un paiement (jamais de suppression) : le reçu reste consultable, marqué ANNULÉ. */
    public function cancel(Payment $payment, User $user, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $user, $reason) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new DomainException('Ce paiement est déjà annulé.');
            }

            $locked->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => trim($reason),
            ])->save();

            return $locked;
        });
    }
}
