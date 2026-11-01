<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPrivateDataVisible = $request->user()->isAdmin()
            || $this->user_id === $request->user()->id;

        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'specialty' => $this->specialty,
            'phone' => $this->when($isPrivateDataVisible, $this->phone),
            'email' => $this->when($isPrivateDataVisible, $this->email),
            'status' => $this->status->value,
        ];
    }
}
