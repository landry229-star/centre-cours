<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CentreSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:max_width=2000,max_height=2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nom du centre',
            'address' => 'adresse',
            'phone' => 'téléphone',
            'email' => 'e-mail',
            'logo' => 'logo du centre',
        ];
    }
}
