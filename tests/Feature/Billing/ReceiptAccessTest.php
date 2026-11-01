<?php

namespace Tests\Feature\Billing;

use App\Models\Guardian;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBilling;
use Tests\TestCase;

/**
 * Règle d'or : un parent ne télécharge JAMAIS le reçu d'un autre parent.
 */
class ReceiptAccessTest extends TestCase
{
    use CreatesBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->goToJanuary2031();
    }

    /** @return array{0: Payment, 1: User} reçu + compte du parent propriétaire */
    private function receiptWithOwner(): array
    {
        $owner = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $owner->id]);
        $enrollment = $this->billingEnrollment(guardian: $guardian);

        return [$this->pay($enrollment, '2030-10-01', 15000), $owner];
    }

    public function test_admin_gets_a_pdf(): void
    {
        [$payment] = $this->receiptWithOwner();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('payments.receipt', $payment))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_download_flag_forces_an_attachment_named_after_the_receipt(): void
    {
        [$payment] = $this->receiptWithOwner();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('payments.receipt', [$payment, 'download' => 1]))
            ->assertOk();

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('recu-'.$payment->receipt_number.'.pdf', $disposition);
    }

    public function test_the_owner_guardian_can_download_their_childs_receipt(): void
    {
        [$payment, $owner] = $this->receiptWithOwner();

        $response = $this->actingAs($owner)->get(route('payments.receipt', $payment))->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_another_guardian_gets_403(): void
    {
        [$payment] = $this->receiptWithOwner();
        $stranger = User::factory()->guardian()->create();
        Guardian::factory()->create(['user_id' => $stranger->id]);

        $this->actingAs($stranger)->get(route('payments.receipt', $payment))->assertForbidden();
    }

    public function test_teacher_and_student_get_403(): void
    {
        [$payment] = $this->receiptWithOwner();

        $this->actingAs(User::factory()->teacher()->create())->get(route('payments.receipt', $payment))->assertForbidden();
        $this->actingAs(User::factory()->student()->create())->get(route('payments.receipt', $payment))->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        [$payment] = $this->receiptWithOwner();

        $this->get(route('payments.receipt', $payment))->assertRedirect(route('login'));
    }

    public function test_deactivated_guardian_is_blocked(): void
    {
        [$payment, $owner] = $this->receiptWithOwner();
        $owner->forceFill(['is_active' => false])->save();

        $this->actingAs($owner)->get(route('payments.receipt', $payment))->assertRedirect();
    }

    public function test_a_cancelled_receipt_is_still_readable_by_the_owner(): void
    {
        [$payment, $owner] = $this->receiptWithOwner();
        app(PaymentService::class)->cancel($payment, User::factory()->admin()->create(), 'Erreur de saisie');

        $response = $this->actingAs($owner)->get(route('payments.receipt', $payment))->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }
}
