<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\PaymentService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isGuardian()) {
            abort(403);
        }

        return PaymentResource::collection(
            $this->visiblePayments($request->user())
                ->with('enrollment.student')
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->paginate(50),
        );
    }

    public function store(PaymentRequest $request, PaymentService $payments): JsonResponse
    {
        $validated = $request->validated();

        try {
            $payment = $payments->record(
                $request->enrollment(),
                Carbon::parse($validated['month']),
                (int) $validated['amount'],
                PaymentMethod::from($validated['method']),
                Carbon::parse($validated['paid_at']),
                $validated['reference'] ?? null,
                $validated['payer_name'] ?? null,
                $request->user(),
            );
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new PaymentResource($payment->load('enrollment.student')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Payment $payment): PaymentResource
    {
        $payment = $this->visiblePayments($request->user())->findOrFail($payment->id);

        return new PaymentResource($payment->load('enrollment.student'));
    }

    private function visiblePayments(User $user): Builder
    {
        $query = Payment::query();

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('enrollment.student.guardian', fn (Builder $guardianQuery) => $guardianQuery
            ->where('user_id', $user->id));
    }
}
