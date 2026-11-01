<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
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
            'day_of_week' => $this->day_of_week->value,
            'starts_at' => $this->startTime(),
            'ends_at' => $this->endTime(),
            'classroom' => $this->whenLoaded('classroom', fn () => [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ]),
            'subject' => $this->whenLoaded('assignment', fn () => $this->assignment?->subject?->name),
            'teacher' => $this->whenLoaded('assignment', fn () => $this->assignment?->teacher?->full_name),
            'room' => $this->whenLoaded('room', fn () => $this->room?->name),
        ];
    }
}
