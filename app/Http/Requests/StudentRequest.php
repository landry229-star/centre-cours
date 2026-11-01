<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sert à la création ET à la modification (le matricule n'est jamais modifiable).
 */
class StudentRequest extends FormRequest
{
    private const PHONE = '/^\+?[0-9\s.\-]{8,20}$/';

    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            ? $this->user()->can('update', $student)
            : $this->user()->can('create', Student::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'last_name' => is_string($this->last_name) ? mb_strtoupper(trim($this->last_name)) : $this->last_name,
            'first_name' => is_string($this->first_name)
                ? mb_convert_case(trim($this->first_name), MB_CASE_TITLE, 'UTF-8')
                : $this->first_name,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guardian_id' => ['required', 'integer', Rule::exists('guardians', 'id')->whereNull('deleted_at')],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_date' => ['required', 'date', 'before:today', 'after:1960-01-01'],
            'birth_place' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'regex:'.self::PHONE],
            'address' => ['nullable', 'string', 'max:255'],
            'guardian_relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'emergency_contact_name' => ['nullable', 'string', 'max:150', 'required_with:emergency_contact_phone'],
            'emergency_contact_phone' => ['nullable', 'regex:'.self::PHONE, 'required_with:emergency_contact_name'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            // image : validée sur le contenu réel ; formats et poids limités.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }
}
