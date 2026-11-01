<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crée un paiement « brut » (sans passer par PaymentService) : pratique pour les tests de lecture.
 * Pour tester les règles métier, appelez PaymentService.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        static $n = 0;
        $n++;

        return [
            'enrollment_id' => Enrollment::factory(),
            'month' => now()->startOfMonth()->toDateString(),
            'amount' => 15000,
            'method' => PaymentMethod::Cash,
            'reference' => null,
            'payer_name' => 'Parent Test',
            'paid_at' => now()->toDateString(),
            'receipt_number' => sprintf('REC-9999-%06d', $n),
            'recorded_by' => User::factory()->admin(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'cancelled_at' => now(),
            'cancelled_by' => User::factory()->admin(),
            'cancel_reason' => 'Erreur de saisie',
        ]);
    }
}
