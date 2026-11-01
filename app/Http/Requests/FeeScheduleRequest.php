<?php

namespace App\Http\Requests;

use App\Models\FeeSchedule;
use Illuminate\Foundation\Http\FormRequest;

class FeeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FeeSchedule::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            // Entier FCFA, borné pour éviter les fautes de frappe (10 000 000 = 7 zéros).
            'monthly_amount' => ['required', 'integer', 'min:1', 'max:10000000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['monthly_amount' => 'tarif mensuel'];
    }

    protected function prepareForValidation(): void
    {
        // « 15 000 » ou « 15000 FCFA » → 15000
        if (is_string($this->monthly_amount)) {
            $this->merge(['monthly_amount' => preg_replace('/[\s\x{00A0}]|FCFA/iu', '', $this->monthly_amount)]);
        }
    }
}
