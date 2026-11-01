<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création : POST students/{student}/enrollments
 * Changement de classe : POST enrollments/{enrollment}/transfer
 * Les règles métier (capacité, doublon...) sont dans EnrollmentService.
 */
class EnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $enrollment instanceof Enrollment
            ? $this->user()->can('update', $enrollment)
            : $this->user()->can('create', Enrollment::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
        ];
    }

    public function student(): ?Student
    {
        $student = $this->route('student');

        return $student instanceof Student ? $student : null;
    }
}
