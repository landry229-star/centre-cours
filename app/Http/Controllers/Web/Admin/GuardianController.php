<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardianRequest;
use App\Models\Guardian;
use App\Services\Accounts\AccountProvisioner;
use App\Services\Guardians\GuardianService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Guardian::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $guardians = Guardian::query()
            ->withCount('students')
            ->search($filters['search'] ?? null)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.guardians.index', compact('guardians'));
    }

    public function create(): View
    {
        Gate::authorize('create', Guardian::class);

        return view('admin.guardians.create');
    }

    public function store(
        GuardianRequest $request,
        GuardianService $service,
        AccountProvisioner $accounts,
    ): RedirectResponse {
        $result = $service->create(
            $request->safe()->except(['create_account']),
            $request->boolean('create_account'),
        );

        $successMessage = "Parent {$result['guardian']->full_name} enregistré.";
        $setupLinkSent = true;

        if ($result['account_created']) {
            $setupLinkSent = $accounts->sendPasswordSetupLink($result['guardian']->user()->firstOrFail());

            if ($setupLinkSent) {
                $successMessage .= ' Un lien de définition du mot de passe a été envoyé par e-mail.';
            }
        }

        $redirect = redirect()
            ->route('admin.guardians.show', $result['guardian'])
            ->with('success', $successMessage);

        if ($result['account_created'] && ! $setupLinkSent) {
            return $redirect->with('error', 'Le compte est créé, mais le lien de définition du mot de passe n’a pas pu être envoyé. Vérifiez la configuration de messagerie puis utilisez « Mot de passe oublié » pour renvoyer le lien.');
        }

        return $redirect;
    }

    public function show(Guardian $guardian): View
    {
        Gate::authorize('view', $guardian);

        $guardian->load(['students', 'user']);

        return view('admin.guardians.show', compact('guardian'));
    }

    public function edit(Guardian $guardian): View
    {
        Gate::authorize('update', $guardian);

        return view('admin.guardians.edit', compact('guardian'));
    }

    public function update(GuardianRequest $request, Guardian $guardian, GuardianService $service): RedirectResponse
    {
        $service->update($guardian, $request->safe()->except(['create_account']));

        return redirect()
            ->route('admin.guardians.show', $guardian)
            ->with('success', 'Fiche parent mise à jour.');
    }

    public function destroy(Guardian $guardian, GuardianService $service): RedirectResponse
    {
        Gate::authorize('delete', $guardian);

        try {
            $service->delete($guardian);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.guardians.index')
            ->with('success', "Parent {$guardian->full_name} archivé.");
    }
}
