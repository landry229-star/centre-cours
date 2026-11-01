<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeeScheduleRequest;
use App\Models\FeeSchedule;
use App\Models\Level;
use App\Models\SchoolYear;
use App\Services\Billing\FeeScheduleService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeeScheduleController extends Controller
{
    public function index(Request $request, FeeScheduleService $service): View
    {
        Gate::authorize('viewAny', FeeSchedule::class);

        $filters = $request->validate(['school_year_id' => ['nullable', 'integer']]);
        $year = isset($filters['school_year_id'])
            ? SchoolYear::find($filters['school_year_id'])
            : SchoolYear::current();
        $year ??= SchoolYear::orderByDesc('start_date')->first();

        $fees = $year
            ? FeeSchedule::where('school_year_id', $year->id)->get()->keyBy('level_id')
            : collect();

        return view('admin.fee-schedules.index', [
            'year' => $year,
            'schoolYears' => SchoolYear::orderByDesc('start_date')->pluck('label', 'id')->all(),
            'levels' => Level::orderBy('position')->get(),
            'fees' => $fees,
            // Tarifs verrouillés : un paiement existe déjà pour ce niveau.
            'locked' => $fees->map(fn (FeeSchedule $fee) => $service->hasPayments($fee))->all(),
        ]);
    }

    public function store(FeeScheduleRequest $request, FeeScheduleService $service): RedirectResponse
    {
        $year = SchoolYear::findOrFail($request->validated('school_year_id'));
        $level = Level::findOrFail($request->validated('level_id'));

        try {
            $service->set($year, $level, (int) $request->validated('monthly_amount'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.fee-schedules.index', ['school_year_id' => $year->id])
            ->with('success', "Tarif de {$level->name} enregistré pour {$year->label}.");
    }

    public function destroy(FeeSchedule $feeSchedule, FeeScheduleService $service): RedirectResponse
    {
        Gate::authorize('delete', $feeSchedule);

        try {
            $service->delete($feeSchedule);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.fee-schedules.index', ['school_year_id' => $feeSchedule->school_year_id])
            ->with('success', 'Tarif supprimé.');
    }
}
