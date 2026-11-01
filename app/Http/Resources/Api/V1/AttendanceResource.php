<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'arrived_at' => $this->arrivalTime(),
            'note' => $this->note,
            'session' => $this->whenLoaded('session', fn () => [
                'id' => $this->session->id,
                'date' => $this->session->session_date->toDateString(),
                'classroom' => $this->session->classroom?->name,
                'subject' => $this->session->subject?->name,
            ]),
            'student' => $this->whenLoaded('enrollment', fn () => [
                'id' => $this->enrollment->student?->id,
                'full_name' => $this->enrollment->student?->full_name,
                'matricule' => $this->enrollment->student?->matricule,
            ]),
        ];
    }
}
