<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
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
            'score' => $this->score,
            'comment' => $this->comment,
            'evaluation' => $this->whenLoaded('evaluation', fn () => [
                'id' => $this->evaluation->id,
                'title' => $this->evaluation->title,
                'type' => $this->evaluation->type->value,
                'date' => $this->evaluation->evaluation_date->toDateString(),
                'coefficient' => $this->evaluation->coefficient,
                'published_at' => $this->evaluation->published_at?->toIso8601String(),
                'subject' => $this->evaluation->assignment?->subject?->name,
            ]),
            'student' => $this->whenLoaded('enrollment', fn () => [
                'id' => $this->enrollment->student?->id,
                'full_name' => $this->enrollment->student?->full_name,
            ]),
        ];
    }
}
