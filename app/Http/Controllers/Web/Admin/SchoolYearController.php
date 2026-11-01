<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolYearRequest;
use App\Models\SchoolYear;
use App\Services\Academics\SchoolYearService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SchoolYearController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SchoolYear::class);

        $years = SchoolYear::query()
            ->withCount([
                'classrooms',
                'enrollments',
                'enrollments as active_enrollments_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->orderByDesc('start_date')
            ->get();

        return view('admin.school-years.index', compact('years'));
    }

    public function create(): View
    {
        Gate::authorize('create', SchoolYear::class);

        return view('admin.school-years.create');
    }

    public function store(SchoolYearRequest $request, SchoolYearService $service): RedirectResponse
    {
        $year = $service->create($request->validated());

        return redirect()
            ->route('admin.school-years.index')
            ->with('success', "Année scolaire {$year->label} créée. Activez-la quand elle commence.");
    }

    public function edit(SchoolYear $schoolYear): View
    {
        Gate::authorize('update', $schoolYear);

        return view('admin.school-years.edit', compact('schoolYear'));
    }

    public function update(SchoolYearRequest $request, SchoolYear $schoolYear, SchoolYearService $service): RedirectResponse
    {
        try {
            $service->update($schoolYear, $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.school-years.index')
            ->with('success', 'Année scolaire mise à jour.');
    }

    public function destroy(SchoolYear $schoolYear, SchoolYearService $service): RedirectResponse
    {
        Gate::authorize('delete', $schoolYear);

        try {
            $service->delete($schoolYear);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.school-years.index')
            ->with('success', "Année scolaire {$schoolYear->label} supprimée.");
    }
}
