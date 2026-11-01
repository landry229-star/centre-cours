<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('attendanceSession');

        return $session instanceof AttendanceSession && $this->user()->can('update', $session);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Clé = identifiant d'inscription ; la vérification « cet élève est bien dans la classe » est dans le service.
            'entries' => ['required', 'array', 'min:1', 'max:300'],
            'entries.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'entries.*.arrived_at' => ['nullable', 'date_format:H:i'],
            'entries.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'entries' => 'feuille de présence',
            'entries.*.status' => 'statut',
            'entries.*.arrived_at' => "heure d'arrivée",
            'entries.*.note' => 'remarque',
        ];
    }
}
