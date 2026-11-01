<?php

namespace App\Http\Requests;

use App\Models\Evaluation;
use Illuminate\Foundation\Http\FormRequest;

class GradeEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $evaluation = $this->route('evaluation');

        return $evaluation instanceof Evaluation && $this->user()->can('update', $evaluation);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1', 'max:300'],
            'entries.*' => ['required', 'array:score,comment'],
            'entries.*.score' => ['present', 'nullable', 'numeric', 'between:0,20'],
            'entries.*.comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'entries' => 'notes',
            'entries.*.score' => 'note sur 20',
            'entries.*.comment' => 'commentaire',
        ];
    }
}
