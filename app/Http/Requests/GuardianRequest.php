<?php

namespace App\Http\Requests;

use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardianRequest extends FormRequest
{
    private const PHONE = '/^\+?[0-9\s.\-]{8,20}$/';

    public function authorize(): bool
    {
        $guardian = $this->route('guardian');

        return $guardian instanceof Guardian
            ? $this->user()->can('update', $guardian)
            : $this->user()->can('create', Guardian::class);
    }

    protected function prepareForValidation(): void
    {
        // PostgreSQL distingue majuscules et minuscules : on normalise les emails en minuscules.
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
        $guardian = $this->route('guardian');

        $email = ['nullable', 'email', 'max:255'];

        if ($guardian instanceof Guardian && $guardian->user_id !== null) {
            // Le parent a un compte : l'email sert de login, il doit rester unique.
            $email = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($guardian->user_id)];
        } elseif ($this->boolean('create_account')) {
            $email = ['required', 'email', 'max:255', Rule::unique('users', 'email')];
        }

        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:'.self::PHONE],
            'email' => $email,
            'address' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:150'],
            'create_account' => ['nullable', 'boolean'],
        ];
    }
}
