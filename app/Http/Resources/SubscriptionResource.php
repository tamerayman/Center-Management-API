<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'monthly_fee' => $this->monthly_fee,
            'monthly_discount' => $this->monthly_discount,
            'discount_reason' => $this->discount_reason,
            'final_monthly_amount' => $this->final_monthly_amount,
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
