<?php

namespace App\Services\Accounts;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Crée des comptes dont le mot de passe est défini via un lien à durée limitée.
 */
class AccountProvisioner
{
    public function provision(string $name, string $email, Role $role): User
    {
        $password = Str::password(64);

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => $password, // haché par le cast 'hashed'
        ]);
        // Rôle affecté explicitement (non fillable).
        $user->role = $role;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->must_change_password = true;
        $user->save();

        return $user;
    }

    public function sendPasswordSetupLink(User $user): bool
    {
        return Password::sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
    }
}
