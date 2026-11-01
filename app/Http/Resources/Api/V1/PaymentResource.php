<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'receipt_number' => $this->receipt_number,
            'month' => $this->month->format('Y-m'),
            'amount' => $this->amount,
            'method' => $this->method->value,
            'reference' => $this->reference,
            'payer_name' => $this->payer_name,
            'paid_at' => $this->paid_at->toDateString(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'student' => $this->whenLoaded('enrollment', fn () => [
                'id' => $this->enrollment->student?->id,
                'full_name' => $this->enrollment->student?->full_name,
            ]),
        ];
    }
}
