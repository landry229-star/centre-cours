<?php

namespace App\Http\Requests;

use App\Enums\EvaluationType;
use App\Models\Evaluation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Evaluation::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(EvaluationType::class)],
            'evaluation_date' => ['required', 'date', 'before_or_equal:today'],
            'coefficient' => ['required', 'integer', 'between:1,10'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'assignment_id' => 'classe et matière',
            'title' => 'intitulé',
            'type' => "type d'évaluation",
            'evaluation_date' => 'date',
            'coefficient' => "coefficient de l'évaluation",
        ];
    }
}
