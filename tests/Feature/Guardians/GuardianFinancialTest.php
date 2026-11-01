<?php

namespace Tests\Feature\Guardians;

use App\Models\FeeDueDate;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\Billing\FeeStatementService;
use App\Services\Billing\PaymentService;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBilling;
use Tests\TestCase;

class GuardianFinancialTest extends TestCase
{
    use CreatesBilling, RefreshDatabase;

    public function test_parent_can_review_child_fee_schedule_arrears_and_payment_history(): void
    {
        $this->travelTo('2030-10-15 12:00:00');
        [$user, $guardian] = $this->guardianAccount();
        $enrollment = $this->billingEnrollment(guardian: $guardian);
        FeeDueDate::factory()->create([
            'student_id' => $enrollment->student_id,
            'school_year_id' => $enrollment->school_year_id,
            'month' => '2030-10-01',
            'due_date' => '2030-10-10',
        ]);
        $validPayment = $this->pay($enrollment, '2030-10-01', 5000);
        $cancelledPayment = $this->pay($enrollment, '2030-10-01', 2000);
        app(PaymentService::class)->cancel(
            $cancelledPayment,
            User::factory()->admin()->create(),
            'Erreur de saisie',
        );

        $this->actingAs($user)->get(route('guardian.payments.index'))
            ->assertOk()
            ->assertSee('Situation financière')
            ->assertSee('Échéanciers par enfant')
            ->assertSee('octobre 2030')
            ->assertSee('Limite : 10/10/2030')
            ->assertSee('Échu')
            ->assertSee('Impayés exigibles')
            ->assertSee(Format::fcfa(10000))
            ->assertSee(Format::fcfa(145000))
            ->assertSee($validPayment->receipt_number)
            ->assertSee(Format::date($validPayment->paid_at))
            ->assertSee($cancelledPayment->receipt_number)
            ->assertSee('Annulé')
            ->assertSee(route('payments.receipt', $validPayment));

        $statement = app(FeeStatementService::class)->forStudent($enrollment->schoolYear, $enrollment->student_id);
        expect($statement?->totalExpected())->toBe(150000)
            ->and($statement?->totalPaid())->toBe(5000)
            ->and($statement?->totalRemaining())->toBe(145000)
            ->and($statement?->arrears()->sum('remaining'))->toBe(10000);
    }

    public function test_parent_financial_history_is_limited_to_its_children_and_school_years(): void
    {
        $this->travelTo('2030-10-15 12:00:00');
        [$user, $guardian] = $this->guardianAccount();
        [, $otherGuardian] = $this->guardianAccount();
        $enrollment = $this->billingEnrollment(guardian: $guardian);
        $otherYear = SchoolYear::factory()->create([
            'label' => '2029-2030',
            'start_date' => '2029-10-01',
            'end_date' => '2030-07-31',
        ]);
        $otherEnrollment = $this->billingEnrollment(
            enrolledAt: '2029-10-05',
            year: $otherYear,
            guardian: $otherGuardian,
        );
        $ownPayment = $this->pay($enrollment, '2030-10-01', 5000);
        $privatePayment = $this->pay($otherEnrollment, '2030-06-01', 5000);

        $this->actingAs($user)->get(route('guardian.payments.index'))
            ->assertOk()
            ->assertSee($ownPayment->receipt_number)
            ->assertDontSee($privatePayment->receipt_number)
            ->assertDontSee($otherEnrollment->student->full_name);

        $this->actingAs($user)->get(route('guardian.payments.index', [
            'school_year_id' => $otherEnrollment->school_year_id,
        ]))->assertNotFound();
    }

    public function test_parent_dashboard_shows_financial_summary_for_own_children(): void
    {
        $this->travelTo('2030-10-15 12:00:00');
        [$user, $guardian] = $this->guardianAccount();
        [, $otherGuardian] = $this->guardianAccount();
        $year = $this->billingYear();
        $year->forceFill(['is_active' => true])->save();
        $enrollment = $this->billingEnrollment(guardian: $guardian, year: $year);
        $otherEnrollment = $this->billingEnrollment(guardian: $otherGuardian, year: $year);
        FeeDueDate::factory()->create([
            'student_id' => $enrollment->student_id,
            'school_year_id' => $year->id,
            'month' => '2030-10-01',
            'due_date' => '2030-10-10',
        ]);
        $this->pay($enrollment, '2030-10-01', 5000);
        $this->pay($otherEnrollment, '2030-10-01', 10000);

        $this->actingAs($user)->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('Situation financière')
            ->assertSee(Format::fcfa(10000))
            ->assertSee(route('guardian.payments.index', ['school_year_id' => $year->id]))
            ->assertDontSee($otherEnrollment->student->full_name);
    }

    public function test_parent_cannot_open_financial_space_without_a_linked_guardian_profile(): void
    {
        $this->actingAs(User::factory()->guardian()->create())
            ->get(route('guardian.payments.index'))
            ->assertForbidden();
    }

    public function test_payment_view_any_is_granted_only_to_admins_and_linked_guardians(): void
    {
        [$guardianUser] = $this->guardianAccount();
        $guardianWithoutProfile = User::factory()->guardian()->create();

        expect($guardianUser->can('viewAny', Payment::class))->toBeTrue()
            ->and(User::factory()->admin()->create()->can('viewAny', Payment::class))->toBeTrue()
            ->and($guardianWithoutProfile->can('viewAny', Payment::class))->toBeFalse()
            ->and(User::factory()->teacher()->create()->can('viewAny', Payment::class))->toBeFalse()
            ->and(User::factory()->student()->create()->can('viewAny', Payment::class))->toBeFalse();
    }

    private function guardianAccount(): array
    {
        $user = User::factory()->guardian()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);

        return [$user, $guardian];
    }
}
