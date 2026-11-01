<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolYear;
use App\Services\Academics\SchoolYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SchoolYearActivationController extends Controller
{
    public function store(SchoolYear $schoolYear, SchoolYearService $service): RedirectResponse
    {
        Gate::authorize('activate', $schoolYear);

        $service->activate($schoolYear);

        return redirect()
            ->route('admin.school-years.index')
            ->with('success', "L'année {$schoolYear->label} est maintenant active. Les inscriptions de l'année précédente sont terminées.");
    }
}
