<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isPrivateDataVisible = $user->isAdmin()
            || ($user->isGuardian() && $this->guardian?->user_id === $user->id)
            || ($user->isStudent() && $this->user_id === $user->id);

        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender->value,
            'birth_date' => $this->when($isPrivateDataVisible, $this->birth_date?->toDateString()),
            'phone' => $this->when($isPrivateDataVisible, $this->phone),
            'address' => $this->when($isPrivateDataVisible, $this->address),
            'status' => $this->status->value,
        ];
    }
}
