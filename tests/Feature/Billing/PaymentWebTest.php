<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Models\FeeDueDate;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBilling;
use Tests\TestCase;

class PaymentWebTest extends TestCase
{
    use CreatesBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->goToJanuary2031();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload($enrollment, array $overrides = []): array
    {
        return array_merge([
            'enrollment_id' => $enrollment->id,
            'month' => '2030-10-01',
            'amount' => '15 000',
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
        ], $overrides);
    }

    public function test_admin_records_a_payment_and_lands_on_its_receipt_page(): void
    {
        $enrollment = $this->billingEnrollment();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.payments.store'), $this->payload($enrollment));

        $payment = Payment::firstOrFail();
        $response->assertRedirect(route('admin.payments.show', $payment));
        $this->assertSame(15000, $payment->amount);
        $this->assertSame($admin->id, $payment->recorded_by);
        $this->assertSame($enrollment->student->guardian->full_name, $payment->payer_name);

        $this->actingAs($admin)->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertSee('Quinze mille francs CFA');
    }

    public function test_reference_is_required_for_mobile_money_but_not_for_cash(): void
    {
        $enrollment = $this->billingEnrollment();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.payments.store'), $this->payload($enrollment, ['method' => 'mobile_money']))
            ->assertSessionHasErrors('reference');
        $this->assertSame(0, Payment::count());

        $this->actingAs($admin)->post(route('admin.payments.store'), $this->payload($enrollment, ['method' => 'cash']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Payment::count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $enrollment = $this->billingEnrollment();
        $admin = $this->admin();

        foreach ([
            ['amount' => '0'],
            ['amount' => 'abc'],
            ['method' => 'bitcoin'],
            ['month' => 'octobre'],
            ['paid_at' => now()->addDays(3)->toDateString()],
            ['enrollment_id' => 999999],
        ] as $override) {
            $this->actingAs($admin)->post(route('admin.payments.store'), $this->payload($enrollment, $override))
                ->assertSessionHasErrors();
        }

        $this->assertSame(0, Payment::count());
    }

    public function test_overpayment_shows_an_error_and_saves_nothing(): void
    {
        $enrollment = $this->billingEnrollment();

        $this->actingAs($this->admin())
            ->from(route('admin.payments.create'))
            ->post(route('admin.payments.store'), $this->payload($enrollment, ['amount' => 20000]))
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::count());
    }

    public function test_non_admin_roles_cannot_touch_payments(): void
    {
        $enrollment = $this->billingEnrollment();
        $payment = $this->pay($enrollment, '2030-10-01', 5000);

        foreach ([User::factory()->teacher()->create(), User::factory()->guardian()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.payments.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.payments.create'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.payments.show', $payment))->assertForbidden();
            $this->actingAs($user)->get(route('admin.payments.arrears'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.enrollments.statement', $enrollment))->assertForbidden();
            $this->actingAs($user)->post(route('admin.payments.store'), $this->payload($enrollment))->assertForbidden();
            $this->actingAs($user)->post(route('admin.payments.cancel', $payment), ['reason' => 'Test de sécurité'])->assertForbidden();
        }

        $this->assertFalse($payment->fresh()->isCancelled());
        $this->assertSame(1, Payment::count());
    }

    public function test_cancel_requires_a_reason(): void
    {
        $payment = $this->pay($this->billingEnrollment(), '2030-10-01', 5000);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.payments.cancel', $payment), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertFalse($payment->fresh()->isCancelled());

        $this->actingAs($admin)->post(route('admin.payments.cancel', $payment), ['reason' => 'Saisi pour le mauvais élève'])
            ->assertRedirect(route('admin.payments.show', $payment));
        $this->assertTrue($payment->fresh()->isCancelled());
    }

    public function test_pages_render_and_filters_work(): void
    {
        $enrollment = $this->billingEnrollment();
        $cashPayment = $this->pay($enrollment, '2030-10-01', 5000);
        $mobileMoneyPayment = $this->pay(
            $enrollment,
            '2030-11-01',
            4000,
            PaymentMethod::MobileMoney,
            'MM-2030-11',
        );
        $admin = $this->admin();
        $name = $enrollment->student->last_name;

        $this->actingAs($admin)
            ->get(route('admin.payments.index', ['school_year_id' => $enrollment->school_year_id]))
            ->assertOk()
            ->assertSee($cashPayment->receipt_number)
            ->assertSee($mobileMoneyPayment->receipt_number)
            ->assertViewHas('total', 9000);

        $this->actingAs($admin)
            ->get(route('admin.payments.index', ['school_year_id' => $enrollment->school_year_id, 'search' => $name]))
            ->assertSee($cashPayment->receipt_number)
            ->assertSee($mobileMoneyPayment->receipt_number);

        $this->actingAs($admin)
            ->get(route('admin.payments.index', [
                'school_year_id' => $enrollment->school_year_id,
                'month' => '2030-11',
                'method' => PaymentMethod::MobileMoney->value,
            ]))
            ->assertSee($mobileMoneyPayment->receipt_number)
            ->assertDontSee($cashPayment->receipt_number)
            ->assertViewHas('total', 4000);

        $this->actingAs($admin)
            ->post(route('admin.payments.cancel', $mobileMoneyPayment), ['reason' => 'Erreur de saisie'])
            ->assertRedirect(route('admin.payments.show', $mobileMoneyPayment));

        $this->actingAs($admin)
            ->get(route('admin.payments.index', [
                'school_year_id' => $enrollment->school_year_id,
                'state' => 'cancelled',
            ]))
            ->assertSee($mobileMoneyPayment->receipt_number)
            ->assertDontSee($cashPayment->receipt_number)
            ->assertViewHas('total', 0);

        $this->actingAs($admin)
            ->get(route('admin.payments.index', [
                'school_year_id' => $enrollment->school_year_id,
                'state' => 'all',
            ]))
            ->assertSee($cashPayment->receipt_number)
            ->assertSee($mobileMoneyPayment->receipt_number)
            ->assertViewHas('total', 5000);

        $this->actingAs($admin)->get(route('admin.enrollments.statement', $enrollment))->assertOk()->assertSee('Montant attendu');
        $this->actingAs($admin)->get(route('admin.payments.create', ['enrollment' => $enrollment->id]))->assertOk()->assertSee('Enregistrer le paiement');
        $this->actingAs($admin)->get(route('admin.payments.create', ['search' => $name]))->assertOk();
        $this->actingAs($admin)->get(route('admin.enrollments.index', ['school_year_id' => $enrollment->school_year_id]))->assertOk()->assertSee('Paiements');
    }

    public function test_payment_list_rejects_unknown_method_filters(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.payments.index', ['method' => 'bitcoin']))
            ->assertSessionHasErrors('method');
    }

    public function test_arrears_page_lists_only_started_unsettled_months(): void
    {
        $enrollment = $this->billingEnrollment();
        foreach (['2030-11-10', '2030-12-10', '2031-01-10'] as $dueDate) {
            FeeDueDate::factory()->create([
                'student_id' => $enrollment->student_id,
                'school_year_id' => $enrollment->school_year_id,
                'month' => Carbon::parse($dueDate)->startOfMonth()->toDateString(),
                'due_date' => $dueDate,
            ]);
        }
        $this->pay($enrollment, '2030-10-01', 15000);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.payments.arrears', ['school_year_id' => $enrollment->school_year_id]))
            ->assertOk()
            ->assertSee($enrollment->student->matricule)
            ->assertSee('novembre 2030')
            ->assertSee('janvier 2031')
            ->assertDontSee('octobre 2030')
            ->assertDontSee('février 2031');

        // Nov + Déc + Jan = 3 × 15 000 = 45 000 FCFA (espaces insécables dans le HTML)
        $response->assertSee("45\u{00A0}000\u{00A0}FCFA", false);
    }

    public function test_admin_can_set_change_and_clear_a_due_date_with_an_audit_trail(): void
    {
        $enrollment = $this->billingEnrollment();
        $admin = $this->admin();
        $url = route('admin.enrollments.due-date', $enrollment);

        $this->actingAs($admin)->patch($url, [
            'month' => '2030-10-01',
            'due_date' => '2030-10-10',
        ])->assertRedirect(route('admin.enrollments.statement', $enrollment));

        $dueDate = FeeDueDate::firstOrFail();
        $this->assertDatabaseHas('fee_due_date_changes', [
            'fee_due_date_id' => $dueDate->id,
            'old_due_date' => null,
            'new_due_date' => '2030-10-10',
            'changed_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch($url, [
            'month' => '2030-10-01',
            'due_date' => '2030-10-10',
        ])->assertSessionHas('success', 'Aucune modification de date limite.');
        $this->assertDatabaseCount('fee_due_date_changes', 1);

        $this->actingAs($admin)->patch($url, [
            'month' => '2030-10-01',
            'due_date' => '2030-10-12',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('fee_due_date_changes', [
            'fee_due_date_id' => $dueDate->id,
            'old_due_date' => '2030-10-10',
            'new_due_date' => '2030-10-12',
            'changed_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch($url, [
            'month' => '2030-10-01',
            'due_date' => '',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('fee_due_date_changes', [
            'fee_due_date_id' => $dueDate->id,
            'old_due_date' => '2030-10-12',
            'new_due_date' => null,
            'changed_by' => $admin->id,
        ]);
        $this->assertNull($dueDate->fresh()->due_date);

        $this->actingAs($admin)->get(route('admin.enrollments.statement', $enrollment))
            ->assertSee('Historique (3)')
            ->assertSee('date effacée');
    }

    public function test_due_date_updates_reject_non_month_start_values_and_non_billable_months(): void
    {
        $enrollment = $this->billingEnrollment();
        $url = route('admin.enrollments.due-date', $enrollment);

        $this->actingAs($this->admin())->patch($url, [
            'month' => '2030-10-02',
            'due_date' => '2030-10-10',
        ])->assertSessionHasErrors('month');

        $this->actingAs($this->admin())
            ->from(route('admin.enrollments.statement', $enrollment))
            ->patch($url, [
                'month' => '2030-09-01',
                'due_date' => '2030-09-10',
            ])
            ->assertSessionHas('error', 'Ce mois ne fait pas partie de l’échéancier de cet élève.');

        $this->assertDatabaseCount('fee_due_dates', 0);
        $this->assertDatabaseCount('fee_due_date_changes', 0);
    }

    public function test_only_administrators_can_change_due_dates(): void
    {
        $enrollment = $this->billingEnrollment();

        $this->actingAs(User::factory()->teacher()->create())
            ->patch(route('admin.enrollments.due-date', $enrollment), [
                'month' => '2030-10-01',
                'due_date' => '2030-10-10',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('fee_due_dates', 0);
    }

    public function test_arrears_page_warns_about_levels_without_a_fee(): void
    {
        $enrollment = $this->billingEnrollment(fee: 0);

        $this->actingAs($this->admin())
            ->get(route('admin.payments.arrears', ['school_year_id' => $enrollment->school_year_id]))
            ->assertOk()
            ->assertSee('faute de tarif');
    }

    public function test_arrears_page_rejects_a_nonexistent_school_year_filter(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.payments.arrears', ['school_year_id' => 99999999]))
            ->assertSessionHasErrors('school_year_id');
    }
}
