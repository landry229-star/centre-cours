<?php

namespace Tests\Concerns;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\User;
use App\Services\Billing\PaymentService;
use Carbon\Carbon;

/**
 * Jeu de données commun aux tests de facturation.
 * Année 2030-2031 (octobre 2030 → juillet 2031) : volontairement loin du présent,
 * pour que les tests ne dépendent jamais de la date réelle du jour.
 */
trait CreatesBilling
{
    protected function billingYear(): SchoolYear
    {
        // Réutilise l'année si elle existe déjà (plusieurs élèves dans le même test).
        return SchoolYear::firstWhere('label', '2030-2031')
            ?? SchoolYear::factory()->create([
                'label' => '2030-2031',
                'start_date' => '2030-10-01',
                'end_date' => '2031-07-31',
            ]);
    }

    /** Une classe d'un NOUVEAU niveau, avec son tarif (0 = aucun tarif défini). */
    protected function billingClassroom(SchoolYear $year, int $fee = 15000): Classroom
    {
        $level = Level::factory()->create();

        if ($fee > 0) {
            FeeSchedule::factory()->create([
                'school_year_id' => $year->id,
                'level_id' => $level->id,
                'monthly_amount' => $fee,
            ]);
        }

        return Classroom::factory()->create(['school_year_id' => $year->id, 'level_id' => $level->id]);
    }

    protected function billingEnrollment(
        int $fee = 15000,
        string $enrolledAt = '2030-10-05',
        ?SchoolYear $year = null,
        ?Guardian $guardian = null,
    ): Enrollment {
        $year ??= $this->billingYear();
        $classroom = $this->billingClassroom($year, $fee);
        $student = Student::factory()->create($guardian ? ['guardian_id' => $guardian->id] : []);

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'enrolled_at' => $enrolledAt,
        ]);
    }

    /** Enregistre un paiement par le service (les règles métier s'appliquent). */
    protected function pay(
        Enrollment $enrollment,
        string $month,
        int $amount,
        PaymentMethod $method = PaymentMethod::Cash,
        ?string $reference = null,
        ?User $by = null,
    ): Payment {
        return app(PaymentService::class)->record(
            $enrollment,
            Carbon::parse($month),
            $amount,
            $method,
            now(),
            $reference,
            null,
            $by ?? User::factory()->admin()->create(),
        );
    }

    /** Fixe « aujourd'hui » au 15 janvier 2031 : octobre à janvier sont échus. */
    protected function goToJanuary2031(): void
    {
        $this->travelTo(Carbon::parse('2031-01-15 10:00:00'));
    }

    protected function endEnrollment(Enrollment $enrollment, EnrollmentStatus $status, string $date): void
    {
        $enrollment->forceFill(['status' => $status, 'ended_at' => $date])->save();
    }
}
