<?php

namespace App\Http\Requests;

use App\Models\Grade;
use Illuminate\Foundation\Http\FormRequest;

class GradeCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grade = $this->route('grade');

        return $grade instanceof Grade
            && ($this->user()?->can('correct', $grade) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'between:0,20'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'score' => 'nouvelle note',
            'reason' => 'motif de correction',
        ];
    }
}
