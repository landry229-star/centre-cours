<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\Billing\FeeDueDateService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeeDueDateController extends Controller
{
    public function update(
        Request $request,
        Enrollment $enrollment,
        FeeDueDateService $service,
    ): RedirectResponse {
        Gate::authorize('create', Payment::class);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m-d', 'regex:/^\d{4}-\d{2}-01$/'],
            'due_date' => ['present', 'nullable', 'date_format:Y-m-d'],
        ]);

        try {
            $changed = $service->update(
                $enrollment,
                Carbon::createFromFormat('Y-m-d', $validated['month']),
                blank($validated['due_date'])
                    ? null
                    : Carbon::createFromFormat('Y-m-d', $validated['due_date']),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.enrollments.statement', $enrollment)
            ->with('success', $changed
                ? 'Date limite mise à jour. La modification est conservée dans l’historique.'
                : 'Aucune modification de date limite.');
    }
}
