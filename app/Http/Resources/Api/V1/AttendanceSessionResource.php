<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceSessionResource extends JsonResource
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
            'date' => $this->session_date->toDateString(),
            'type' => $this->session_type->value,
            'starts_at' => $this->startTime(),
            'ends_at' => $this->endTime(),
            'classroom' => $this->whenLoaded('classroom', fn () => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ]),
            'subject' => $this->whenLoaded('subject', fn () => $this->subject?->name),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->teacher?->full_name),
        ];
    }
}
