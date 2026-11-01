<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /** L'administration voit tout l'historique ; un parent ne voit que les paiements de ses enfants. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isGuardian() && $user->guardian !== null);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    /** Admin ; le parent de CET élève. Jamais un autre parent, jamais l'élève ni l'enseignant. */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isGuardian()) {
            return false;
        }

        $guardian = $payment->enrollment?->student?->guardian;

        return $guardian !== null && (int) $guardian->user_id === $user->id;
    }

    /** Même règle que view : consulter le reçu = pouvoir le télécharger. */
    public function downloadReceipt(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
