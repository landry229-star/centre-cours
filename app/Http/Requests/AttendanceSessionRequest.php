<?php

namespace App\Http\Requests;

use App\Models\AttendanceSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', AttendanceSession::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'session_type' => ['required', Rule::in(['daily', 'lesson'])],
            'assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'date' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'starts_at' => ['required_if:session_type,lesson', 'prohibited_if:session_type,daily', 'date_format:H:i'],
            'ends_at' => ['prohibited_if:session_type,daily', 'nullable', 'date_format:H:i', 'after:starts_at'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'session_type' => 'type de présence',
            'assignment_id' => 'classe et matière',
            'date' => 'date de la séance',
            'starts_at' => 'heure de début',
            'ends_at' => 'heure de fin',
        ];
    }
}
