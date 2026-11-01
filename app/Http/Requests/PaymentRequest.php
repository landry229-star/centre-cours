<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Les règles de fond (reste à payer, mois facturable) sont dans PaymentService. */
class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'month' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_at' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'reference' => [
                Rule::requiredIf(fn () => in_array($this->input('method'), ['mobile_money', 'bank_transfer'], true)),
                'nullable', 'string', 'max:100',
            ],
            'payer_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'enrollment_id' => 'inscription',
            'month' => 'mois concerné',
            'amount' => 'montant',
            'method' => 'mode de paiement',
            'paid_at' => 'date de paiement',
            'reference' => 'référence',
            'payer_name' => 'nom du payeur',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->amount)) {
            $this->merge(['amount' => preg_replace('/[\s\x{00A0}]/u', '', $this->amount)]);
        }
    }

    public function enrollment(): Enrollment
    {
        return Enrollment::findOrFail($this->validated('enrollment_id'));
    }
}
