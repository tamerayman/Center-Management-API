<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonthlyChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period' => [
                'year' => $this->period_year,
                'month' => $this->period_month,
            ],
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
            ]),
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'title' => $this->course->title,
            ]),
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'credit_applied' => $this->credit_applied,
            'remaining' => $this->remaining_amount,
            'status' => $this->status,
            'due_date' => $this->due_date,
        ];
    }
}
