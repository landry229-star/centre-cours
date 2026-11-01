<?php

namespace App\Http\Requests;

use App\Models\GuardianConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuardianMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', GuardianConversation::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isReply = $this->route('conversation') instanceof GuardianConversation;
        $guardianRules = $this->user()->isAdmin() && ! $isReply
            ? ['required', 'integer', Rule::exists('guardians', 'id')->whereNull('deleted_at')]
            : ['prohibited'];

        return [
            'guardian_id' => $guardianRules,
            'body' => ['required', 'string', 'max:4000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'guardian_id' => 'parent destinataire',
            'body' => 'message',
        ];
    }
}
