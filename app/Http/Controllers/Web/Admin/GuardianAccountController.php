<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\Accounts\AccountProvisioner;
use App\Services\Guardians\GuardianService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class GuardianAccountController extends Controller
{
    public function store(
        Guardian $guardian,
        GuardianService $service,
        AccountProvisioner $accounts,
    ): RedirectResponse {
        Gate::authorize('update', $guardian);

        try {
            $service->createAccount($guardian);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $setupLinkSent = $accounts->sendPasswordSetupLink($guardian->user()->firstOrFail());

        $redirect = redirect()->route('admin.guardians.show', $guardian);

        if (! $setupLinkSent) {
            return $redirect->with('error', 'Le compte est créé, mais le lien de définition du mot de passe n’a pas pu être envoyé. Vérifiez la configuration de messagerie puis utilisez « Mot de passe oublié » pour renvoyer le lien.');
        }

        return $redirect->with('success', 'Accès créé. Un lien de définition du mot de passe a été envoyé par e-mail.');
    }
}
