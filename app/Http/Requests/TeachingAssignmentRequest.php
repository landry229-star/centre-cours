<?php

namespace App\Http\Requests;

use App\Enums\TeacherStatus;
use App\Models\Classroom;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création : POST classrooms/{classroom}/assignments
 * Modification : PUT assignments/{assignment}
 */
class TeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof TeachingAssignment
            ? $this->user()->can('update', $assignment)
            : $this->user()->can('create', TeachingAssignment::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $assignment = $this->route('assignment');
        $classroom = $this->route('classroom');

        $classroomId = $assignment instanceof TeachingAssignment
            ? $assignment->classroom_id
            : ($classroom instanceof Classroom ? $classroom->id : null);

        return [
            // Une matière = un seul enseignant par classe.
            'subject_id' => [
                'required', 'integer', Rule::exists('subjects', 'id'),
                Rule::unique('teaching_assignments', 'subject_id')
                    ->where('classroom_id', $classroomId)
                    ->ignore($assignment instanceof TeachingAssignment ? $assignment->id : null),
            ],
            'teacher_id' => [
                'required', 'integer', Rule::exists('teachers', 'id')->whereNull('deleted_at'),
                // Seul un enseignant ACTIF peut recevoir une nouvelle affectation
                // (on tolère l'enseignant déjà affecté pour ne pas bloquer les autres modifications).
                function (string $attribute, mixed $value, \Closure $fail) use ($assignment) {
                    if ($assignment instanceof TeachingAssignment && (int) $value === $assignment->teacher_id) {
                        return;
                    }

                    $teacher = Teacher::find($value);

                    if ($teacher !== null && $teacher->status !== TeacherStatus::Active) {
                        $fail("Cet enseignant n'est pas actif.");
                    }
                },
            ],
            'coefficient' => ['required', 'integer', 'between:1,10'],
            'weekly_hours' => ['nullable', 'integer', 'between:1,40'],
        ];
    }
}
