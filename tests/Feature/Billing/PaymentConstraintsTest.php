<?php

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PostgreSQL est le dernier rempart : ces tests contournent le service exprès.
 * Une seule instruction qui échoue par test (une transaction PostgreSQL avortée est inutilisable).
 */
class PaymentConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_amount_must_be_positive(): void
    {
        $this->expectException(QueryException::class);
        Payment::factory()->create(['amount' => 0]);
    }

    public function test_month_must_be_the_first_day(): void
    {
        $this->expectException(QueryException::class);
        Payment::factory()->create(['month' => '2030-10-15']);
    }

    public function test_method_must_be_known(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('payments')->where('id', $payment->id)->update(['method' => 'bitcoin']);
    }

    public function test_receipt_number_is_unique(): void
    {
        Payment::factory()->create(['receipt_number' => 'REC-2030-000001']);

        $this->expectException(QueryException::class);
        Payment::factory()->create(['receipt_number' => 'REC-2030-000001']);
    }

    public function test_cancellation_fields_go_together(): void
    {
        $this->expectException(QueryException::class);
        // Annulé sans auteur ni motif : refusé.
        Payment::factory()->create(['cancelled_at' => now()]);
    }

    public function test_valid_cancelled_row_is_accepted(): void
    {
        Payment::factory()->cancelled()->create();

        $this->assertSame(1, Payment::whereNotNull('cancelled_at')->count());
    }

    public function test_same_mobile_money_reference_is_refused_for_two_valid_payments(): void
    {
        $recorder = User::factory()->admin()->create();
        Payment::factory()->create(['method' => 'mobile_money', 'reference' => 'MP-1', 'recorded_by' => $recorder->id]);

        $this->expectException(QueryException::class);
        Payment::factory()->create(['method' => 'mobile_money', 'reference' => 'MP-1', 'recorded_by' => $recorder->id]);
    }

    public function test_cash_payments_may_share_a_reference(): void
    {
        Payment::factory()->count(2)->create(['method' => 'cash', 'reference' => 'CAISSE']);

        $this->assertSame(2, Payment::count());
    }
}
