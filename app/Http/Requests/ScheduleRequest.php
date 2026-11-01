<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $schedule = $this->route('schedule');

        return $schedule instanceof Schedule
            ? $this->user()->can('update', $schedule)
            : $this->user()->can('create', Schedule::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'day_of_week' => ['required', Rule::enum(DayOfWeek::class)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'assignment_id' => 'classe, matière et enseignant',
            'room_id' => 'salle',
            'day_of_week' => 'jour',
            'starts_at' => 'heure de début',
            'ends_at' => 'heure de fin',
        ];
    }
}
