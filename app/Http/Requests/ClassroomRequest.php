<?php

namespace App\Http\Requests;

use App\Enums\ClassroomStatus;
use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classroom = $this->route('classroom');

        return $classroom instanceof Classroom
            ? $this->user()->can('update', $classroom)
            : $this->user()->can('create', Classroom::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $classroom = $this->route('classroom');
        $ignore = $classroom instanceof Classroom ? $classroom->id : null;

        return [
            'school_year_id' => ['required', 'integer', Rule::exists('school_years', 'id')],
            'level_id' => ['required', 'integer', Rule::exists('levels', 'id')],
            // Nom unique par année scolaire (classes archivées ignorées, comme l'index PostgreSQL).
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('classrooms', 'name')
                    ->where('school_year_id', $this->input('school_year_id'))
                    ->whereNull('deleted_at')
                    ->ignore($ignore),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'head_teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(ClassroomStatus::class)],
        ];
    }
}
