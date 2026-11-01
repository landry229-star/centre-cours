<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolYearPeriodRequest;
use App\Models\SchoolYear;
use App\Services\Grades\SchoolYearPeriodService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SchoolYearPeriodController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('admin.academic-periods.index', [
            'schoolYears' => SchoolYear::query()->orderByDesc('start_date')->paginate(20),
        ]);
    }

    public function edit(SchoolYear $schoolYear, SchoolYearPeriodService $periods): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $periods->ensureDefaults($schoolYear);

        return view('admin.academic-periods.edit', [
            'schoolYear' => $schoolYear,
            'periods' => $periods->periods($schoolYear),
            'isLocked' => $periods->isLocked($schoolYear),
        ]);
    }

    public function update(SchoolYearPeriodRequest $request, SchoolYear $schoolYear, SchoolYearPeriodService $periods): RedirectResponse
    {
        try {
            $periods->save($schoolYear, $request->validated('periods'));
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.academic-periods.edit', $schoolYear)
            ->with('success', 'Les dates des périodes ont été enregistrées.');
    }
}
