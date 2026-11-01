<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPaymentRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Services\Billing\FeeStatementService;
use App\Services\Billing\PaymentService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'school_year_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'state' => ['nullable', 'in:valid,cancelled,all'],
        ]);

        $yearId = $request->has('school_year_id')
            ? ($filters['school_year_id'] ?? null)
            : SchoolYear::current()?->id;
        $method = PaymentMethod::tryFrom($filters['method'] ?? '');
        $state = $filters['state'] ?? 'valid';
        $search = $filters['search'] ?? null;

        $query = Payment::query()
            ->when($yearId, fn ($q, $id) => $q->whereHas('enrollment', fn ($e) => $e->where('school_year_id', $id)))
            ->when($filters['month'] ?? null, fn ($q, $m) => $q->where('month', $m.'-01'))
            ->when($method, fn ($q, $m) => $q->where('method', $m->value))
            ->when($state === 'valid', fn ($q) => $q->valid())
            ->when($state === 'cancelled', fn ($q) => $q->whereNotNull('cancelled_at'))
            ->when($search, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w
                    ->where('receipt_number', 'ilike', $like)
                    ->orWhere('reference', 'ilike', $like)
                    ->orWhereHas('enrollment.student', fn ($s) => $s->search($term)));
            });

        $total = (clone $query)->valid()->sum('amount');

        $payments = $query
            ->with(['enrollment.student', 'enrollment.classroom', 'recorder'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'total' => (int) $total,
            'yearId' => $yearId,
            'state' => $state,
            'schoolYears' => SchoolYear::orderByDesc('start_date')->pluck('label', 'id')->all(),
        ]);
    }

    /** Étape 1 : choisir l'élève (recherche). Étape 2 : formulaire pour l'inscription choisie. */
    public function create(Request $request, FeeStatementService $statements): View
    {
        Gate::authorize('create', Payment::class);

        $request->validate([
            'enrollment' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $enrollment = $request->integer('enrollment')
            ? Enrollment::with(['student.guardian', 'classroom.level', 'schoolYear'])->findOrFail($request->integer('enrollment'))
            : null;

        if ($enrollment === null) {
            $search = $request->input('search');
            $candidates = $search
                ? Enrollment::query()
                    ->whereIn('school_year_id', SchoolYear::active()->select('id'))
                    ->whereHas('student', fn ($s) => $s->search($search))
                    ->with(['student', 'classroom'])
                    ->orderByDesc('enrolled_at')
                    ->limit(15)
                    ->get()
                : collect();

            return view('admin.payments.create', ['enrollment' => null, 'candidates' => $candidates, 'search' => $search]);
        }

        return view('admin.payments.create', [
            'enrollment' => $enrollment,
            'statement' => $statements->forStudent($enrollment->schoolYear, $enrollment->student_id),
            'selectedMonth' => $request->input('month'),
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function store(PaymentRequest $request, PaymentService $service): RedirectResponse
    {
        try {
            $payment = $service->record(
                $request->enrollment(),
                Carbon::createFromFormat('Y-m-d', $request->validated('month'))->startOfDay(),
                (int) $request->validated('amount'),
                PaymentMethod::from($request->validated('method')),
                Carbon::parse($request->validated('paid_at')),
                $request->validated('reference'),
                $request->validated('payer_name'),
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.payments.show', $payment)
            ->with('success', "Paiement enregistré. Reçu n° {$payment->receipt_number}.");
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load(['enrollment.student.guardian', 'enrollment.classroom.level', 'enrollment.schoolYear', 'recorder', 'canceller']);

        return view('admin.payments.show', ['payment' => $payment]);
    }

    public function cancel(CancelPaymentRequest $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        try {
            $service->cancel($payment, $request->user(), $request->validated('reason'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.payments.show', $payment)
            ->with('success', 'Paiement annulé. Le reçu reste consultable, marqué « ANNULÉ ».');
    }
}
