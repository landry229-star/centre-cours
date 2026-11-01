<?php

namespace App\Http\Requests;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\TeacherStatus;
use App\Models\Teacher;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    private const PHONE = '/^\+?[0-9\s.\-]{8,20}$/';

    public function authorize(): bool
    {
        $teacher = $this->route('teacher');

        return $teacher instanceof Teacher
            ? $this->user()->can('update', $teacher)
            : $this->user()->can('create', Teacher::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) && trim($this->email) !== '' ? mb_strtolower(trim($this->email)) : null,
            'last_name' => is_string($this->last_name) ? mb_strtoupper(trim($this->last_name)) : $this->last_name,
            'first_name' => is_string($this->first_name)
                ? mb_convert_case(trim($this->first_name), MB_CASE_TITLE, 'UTF-8')
                : $this->first_name,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $teacher = $this->route('teacher');

        $uniqueTeacherEmail = function (string $attribute, mixed $value, Closure $fail) use ($teacher): void {
            if (! is_string($value)) {
                return;
            }

            $query = Teacher::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($value)]);

            if ($teacher instanceof Teacher) {
                $query->where('id', '<>', $teacher->getKey());
            }

            if ($query->exists()) {
                $fail(__('validation.unique', ['attribute' => $attribute]));
            }
        };

        $email = ['nullable', 'email', 'max:255', $uniqueTeacherEmail];

        $status = ['required', Rule::enum(TeacherStatus::class)];

        if ($this->filled('employment_ended_at')) {
            $status[] = Rule::in([TeacherStatus::Inactive->value]);
        }

        if ($this->boolean('create_account')) {
            $status[] = Rule::in([TeacherStatus::Active->value]);
        }

        if ($teacher instanceof Teacher && $teacher->user_id !== null) {
            $email = [
                'required',
                'email',
                'max:255',
                ...$email,
                Rule::unique('users', 'email')->ignore($teacher->user_id),
            ];
        } elseif ($this->boolean('create_account')) {
            $email = [
                'required',
                'email',
                'max:255',
                ...$email,
                Rule::unique('users', 'email'),
            ];
        }

        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'phone' => ['required', 'regex:'.self::PHONE],
            'email' => $email,
            'address' => ['nullable', 'string', 'max:255'],
            'specialty' => ['required', 'string', 'max:150'],
            'education_level' => ['required', Rule::enum(EducationLevel::class)],
            'hired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'employment_ended_at' => ['nullable', 'date', 'after_or_equal:hired_at', 'before_or_equal:today'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150', 'required_with:emergency_contact_phone'],
            'emergency_contact_phone' => ['nullable', 'regex:'.self::PHONE, 'required_with:emergency_contact_name'],
            'status' => $status,
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'create_account' => ['nullable', 'boolean'],
        ];
    }
}
