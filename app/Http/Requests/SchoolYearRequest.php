<?php

namespace App\Http\Requests;

use App\Models\SchoolYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        $year = $this->route('schoolYear');

        return $year instanceof SchoolYear
            ? $this->user()->can('update', $year)
            : $this->user()->can('create', SchoolYear::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => is_string($this->label) ? trim($this->label) : $this->label,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $year = $this->route('schoolYear');
        $ignore = $year instanceof SchoolYear ? $year->id : null;

        return [
            'label' => [
                'required', 'string', 'regex:/^\d{4}-\d{4}$/',
                Rule::unique('school_years', 'label')->ignore($ignore),
                // « 2026-2027 » : la seconde année suit la première.
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (preg_match('/^(\d{4})-(\d{4})$/', (string) $value, $m) && (int) $m[2] !== (int) $m[1] + 1) {
                        $fail('Le libellé doit être de la forme 2026-2027 (années consécutives).');
                    }
                },
            ],
            'start_date' => ['required', 'date'],
            'end_date' => [
                'required', 'date', 'after:start_date',
                // Même règle que la contrainte d'exclusion PostgreSQL, avec un message lisible.
                function (string $attribute, mixed $value, \Closure $fail) use ($ignore) {
                    $start = $this->input('start_date');

                    if (! $start || ! strtotime((string) $start) || ! strtotime((string) $value)) {
                        return;
                    }

                    $overlap = SchoolYear::query()
                        ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
                        ->whereDate('start_date', '<=', $value)
                        ->whereDate('end_date', '>=', $start)
                        ->first();

                    if ($overlap !== null) {
                        $fail("Ces dates chevauchent l'année {$overlap->label}.");
                    }
                },
            ],
        ];
    }
}
