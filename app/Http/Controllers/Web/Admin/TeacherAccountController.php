<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\TeacherStatus;
use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Services\Accounts\AccountProvisioner;
use App\Services\Teachers\TeacherService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TeacherAccountController extends Controller
{
    public function store(
        Teacher $teacher,
        TeacherService $service,
        AccountProvisioner $accounts,
    ): RedirectResponse {
        Gate::authorize('update', $teacher);

        try {
            $service->createAccount($teacher);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $setupLinkSent = $accounts->sendPasswordSetupLink($teacher->user()->firstOrFail());

        $redirect = redirect()->route('admin.teachers.show', $teacher);

        if (! $setupLinkSent) {
            return $redirect->with('error', 'Le compte est créé, mais le lien de définition du mot de passe n’a pas pu être envoyé. Vérifiez la configuration de messagerie puis utilisez « Mot de passe oublié » pour renvoyer le lien.');
        }

        return $redirect->with('success', 'Accès créé. Un lien de définition du mot de passe a été envoyé par e-mail.');
    }

    public function update(Teacher $teacher, TeacherService $service): RedirectResponse
    {
        Gate::authorize('update', $teacher);

        try {
            $service->reactivateAccount($teacher);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.teachers.show', $teacher)
            ->with('success', 'Accès enseignant réactivé.');
    }

    public function resendSetupLink(
        Teacher $teacher,
        AccountProvisioner $accounts,
    ): RedirectResponse {
        Gate::authorize('update', $teacher);

        $user = $teacher->user;

        if (
            $user === null
            || ! $user->is_active
            || ! $user->must_change_password
            || $teacher->status !== TeacherStatus::Active
            || $teacher->employment_ended_at !== null
        ) {
            return redirect()
                ->route('admin.teachers.show', $teacher)
                ->with('error', 'Le lien de définition ne peut être renvoyé que pour un accès actif en attente de configuration.');
        }

        if (! $accounts->sendPasswordSetupLink($user)) {
            return redirect()
                ->route('admin.teachers.show', $teacher)
                ->with('error', 'Le lien n’a pas pu être envoyé. Vérifiez la configuration de messagerie et réessayez.');
        }

        return redirect()
            ->route('admin.teachers.show', $teacher)
            ->with('success', 'Un nouveau lien de définition du mot de passe a été envoyé par e-mail.');
    }
}
