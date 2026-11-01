<?php

namespace Tests\Feature\Billing;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\FeeDueDate;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\FeeStatementService;
use App\Services\Billing\PaymentService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBilling;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use CreatesBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->goToJanuary2031();
    }

    private function statement(Enrollment $enrollment)
    {
        return app(FeeStatementService::class)->forStudent($enrollment->schoolYear, $enrollment->student_id);
    }

    private function row(Enrollment $enrollment, string $month)
    {
        return $this->statement($enrollment)->rows->first(fn ($r) => $r->month->toDateString() === $month);
    }

    public function test_a_full_payment_settles_the_month_and_gets_a_receipt_number(): void
    {
        $enrollment = $this->billingEnrollment();

        $payment = $this->pay($enrollment, '2030-10-01', 15000);

        $this->assertSame('REC-2031-000001', $payment->receipt_number);
        $this->assertSame('2030-10-01', $payment->month->toDateString());
        $this->assertSame(PaymentStatus::Paid, $this->row($enrollment, '2030-10-01')->status);
        $this->assertSame(0, $this->row($enrollment, '2030-10-01')->remaining);
    }

    public function test_partial_payments_add_up_until_the_month_is_paid(): void
    {
        $enrollment = $this->billingEnrollment();

        $this->pay($enrollment, '2030-10-01', 5000);
        $row = $this->row($enrollment, '2030-10-01');
        $this->assertSame(PaymentStatus::Partial, $row->status);
        $this->assertSame(10000, $row->remaining);

        $this->pay($enrollment, '2030-10-01', 10000);
        $this->assertSame(PaymentStatus::Paid, $this->row($enrollment, '2030-10-01')->status);
        $this->assertSame(PaymentStatus::Unpaid, $this->row($enrollment, '2030-11-01')->status);
    }

    public function test_overpayment_is_rejected_and_consumes_no_receipt_number(): void
    {
        $enrollment = $this->billingEnrollment();
        $first = $this->pay($enrollment, '2030-10-01', 10000);

        try {
            $this->pay($enrollment, '2030-10-01', 6000); // reste 5 000
            $this->fail('Le dépassement aurait dû être refusé.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('dépasse le reste à payer', $e->getMessage());
        }

        $second = $this->pay($enrollment, '2030-10-01', 5000);

        $this->assertSame(2, Payment::count());
        $this->assertSame('REC-2031-000001', $first->receipt_number);
        // Pas de trou : l'échec n'a pas consommé le numéro 2.
        $this->assertSame('REC-2031-000002', $second->receipt_number);
    }

    public function test_a_settled_month_cannot_be_paid_again(): void
    {
        $enrollment = $this->billingEnrollment();
        $this->pay($enrollment, '2030-10-01', 15000);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('déjà entièrement payée');
        $this->pay($enrollment, '2030-10-01', 1000);
    }

    public function test_months_outside_the_enrollment_period_are_not_billable(): void
    {
        $enrollment = $this->billingEnrollment(enrolledAt: '2030-12-03');

        $months = $this->statement($enrollment)->rows->map(fn ($r) => $r->month->format('Y-m'))->all();
        $this->assertSame('2030-12', $months[0]);
        $this->assertNotContains('2030-11', $months);

        foreach (['2030-11-01', '2031-08-01'] as $invalid) {
            try {
                $this->pay($enrollment, $invalid, 1000);
                $this->fail("Le mois {$invalid} aurait dû être refusé.");
            } catch (DomainException $e) {
                $this->assertStringContainsString('pas facturable', $e->getMessage());
            }
        }

        $this->assertSame(0, Payment::count());
    }

    public function test_without_a_fee_there_is_no_statement_and_no_payment(): void
    {
        $enrollment = $this->billingEnrollment(fee: 0);

        $statement = $this->statement($enrollment);
        $this->assertNotNull($statement->error);
        $this->assertTrue($statement->rows->isEmpty());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Aucun tarif défini');
        $this->pay($enrollment, '2030-10-01', 1000);
    }

    public function test_future_payment_dates_are_rejected(): void
    {
        $enrollment = $this->billingEnrollment();

        $this->expectException(DomainException::class);
        app(PaymentService::class)->record(
            $enrollment, Carbon::parse('2030-10-01'), 1000, PaymentMethod::Cash,
            now()->addDay(), null, null, User::factory()->admin()->create(),
        );
    }

    public function test_cancelling_reopens_the_month_and_keeps_the_record(): void
    {
        $enrollment = $this->billingEnrollment();
        $admin = User::factory()->admin()->create();
        $payment = $this->pay($enrollment, '2030-10-01', 15000);

        app(PaymentService::class)->cancel($payment, $admin, 'Erreur de saisie');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'cancel_reason' => 'Erreur de saisie']);
        $this->assertTrue($payment->fresh()->isCancelled());
        $this->assertSame(PaymentStatus::Unpaid, $this->row($enrollment, '2030-10-01')->status);

        // Le mois peut être encaissé à nouveau ; le numéro de reçu suivant est bien nouveau.
        $again = $this->pay($enrollment, '2030-10-01', 15000);
        $this->assertSame('REC-2031-000002', $again->receipt_number);
    }

    public function test_a_payment_cannot_be_cancelled_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = $this->pay($this->billingEnrollment(), '2030-10-01', 5000);
        app(PaymentService::class)->cancel($payment, $admin, 'Erreur de saisie');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('déjà annulé');
        app(PaymentService::class)->cancel($payment, $admin, 'Encore');
    }

    public function test_a_transaction_reference_cannot_be_reused_but_can_after_cancellation(): void
    {
        $admin = User::factory()->admin()->create();
        $a = $this->billingEnrollment();
        $b = $this->billingEnrollment();

        $first = $this->pay($a, '2030-10-01', 5000, PaymentMethod::MobileMoney, 'MP-123');

        try {
            $this->pay($b, '2030-10-01', 5000, PaymentMethod::MobileMoney, 'MP-123');
            $this->fail('La référence dupliquée aurait dû être refusée.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('référence', $e->getMessage());
        }

        app(PaymentService::class)->cancel($first, $admin, 'Mauvais élève');
        $this->pay($b, '2030-10-01', 5000, PaymentMethod::MobileMoney, 'MP-123');

        $this->assertSame(1, Payment::valid()->count());
    }

    public function test_class_change_applies_the_new_level_fee_from_the_transfer_month(): void
    {
        $year = $this->billingYear();
        $old = $this->billingEnrollment(fee: 10000, year: $year);
        $this->endEnrollment($old, EnrollmentStatus::Transferred, '2030-12-10');

        $newClassroom = $this->billingClassroom($year, 20000);
        $new = Enrollment::factory()->create([
            'student_id' => $old->student_id,
            'classroom_id' => $newClassroom->id,
            'enrolled_at' => '2030-12-10',
        ]);

        $rows = $this->statement($new)->rows->keyBy(fn ($r) => $r->month->format('Y-m'));

        $this->assertSame(10000, $rows['2030-10']->expected);
        $this->assertSame(10000, $rows['2030-11']->expected);
        $this->assertSame(20000, $rows['2030-12']->expected); // mois du transfert : nouvelle classe
        $this->assertSame(20000, $rows['2031-07']->expected);
        $this->assertSame($new->id, $rows['2030-12']->enrollmentId);
        $this->assertSame($old->id, $rows['2030-10']->enrollmentId);
    }

    public function test_a_withdrawn_student_is_billed_until_the_withdrawal_month_only(): void
    {
        $enrollment = $this->billingEnrollment();
        $this->endEnrollment($enrollment, EnrollmentStatus::Withdrawn, '2030-12-20');

        $months = $this->statement($enrollment)->rows->map(fn ($r) => $r->month->format('Y-m'))->all();

        $this->assertSame(['2030-10', '2030-11', '2030-12'], $months);
    }

    public function test_arrears_only_list_unsettled_months_past_their_configured_deadlines(): void
    {
        $enrollment = $this->billingEnrollment(); // aujourd'hui = 15 janvier 2031
        foreach (['2030-10-10', '2030-11-10', '2030-12-10', '2031-01-10'] as $dueDate) {
            FeeDueDate::factory()->create([
                'student_id' => $enrollment->student_id,
                'school_year_id' => $enrollment->school_year_id,
                'month' => Carbon::parse($dueDate)->startOfMonth()->toDateString(),
                'due_date' => $dueDate,
            ]);
        }
        $this->pay($enrollment, '2030-10-01', 15000);
        $this->pay($enrollment, '2030-11-01', 5000);

        $arrears = $this->statement($enrollment)->arrears();

        $this->assertSame(['2030-11', '2030-12', '2031-01'], $arrears->map(fn ($r) => $r->month->format('Y-m'))->all());
        $this->assertSame(10000, $arrears->first()->remaining);
        // Février n'a pas encore de date limite configurée.
        $this->assertFalse($this->row($enrollment, '2031-02-01')->isArrear());
    }

    public function test_a_month_is_not_overdue_on_its_due_date_but_is_overdue_the_following_day(): void
    {
        $enrollment = $this->billingEnrollment();
        FeeDueDate::factory()->create([
            'student_id' => $enrollment->student_id,
            'school_year_id' => $enrollment->school_year_id,
            'month' => '2031-01-01',
            'due_date' => '2031-01-15',
        ]);

        $this->travelTo('2031-01-15 12:00:00');
        $dueToday = $this->row($enrollment, '2031-01-01');
        $this->assertTrue($dueToday->due);
        $this->assertFalse($dueToday->isArrear());

        $this->travelTo('2031-01-16 00:00:00');
        $this->assertTrue($this->row($enrollment, '2031-01-01')->isArrear());
    }
}
