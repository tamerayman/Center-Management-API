<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAnswerResource extends JsonResource
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
    'question_id' => $this->question_id,
    'student_answer' => $this->student_answer,
    // 'is_correct' => $this->is_correct,
      ];
    }
}
