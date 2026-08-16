<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResultResource extends JsonResource
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
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'name' => $this->student->name,
                ];
            }),
            'exam' => $this->whenLoaded('exam', function () {
                return [
                    'id' => $this->exam->id,
                    'title' => $this->exam->title,
                ];
            }),
            'score' => $this->score,
            'total_marks' => $this->total_marks,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
        ];
    }
}
