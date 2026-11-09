<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
            ]),
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'title' => $this->course->title,
            ]),
            'charge_period' => $this->whenLoaded('charge', fn () => $this->charge ? [
                'year' => $this->charge->period_year,
                'month' => $this->charge->period_month,
            ] : null),
            'amount' => $this->amount,
            'allocated_amount' => $this->allocated_amount,
            'paid_at' => $this->paid_at,
            'method' => $this->method,
            'status' => $this->status,
            'recorded_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'note' => $this->note,
            'cancelled_by' => $this->whenLoaded('canceller', fn () => $this->canceller?->name),
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
        ];
    }
}
