<?php

namespace App\Http\Requests;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject
            ? $this->user()->can('update', $subject)
            : $this->user()->can('create', Subject::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'code' => is_string($this->code) ? mb_strtoupper(trim($this->code)) : $this->code,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $subject = $this->route('subject');
        $ignore = $subject instanceof Subject ? $subject->id : null;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects', 'name')->ignore($ignore)],
            'code' => ['required', 'string', 'max:10', 'alpha_dash', Rule::unique('subjects', 'code')->ignore($ignore)],
        ];
    }
}
