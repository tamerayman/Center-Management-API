<?php

namespace App\Http\Resources;

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
            'status' => $this->status,
            'lesson' => $this->whenLoaded('lesson', function () {
                $lesson = $this->lesson;

                $data = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'lesson_date' => $lesson->lesson_date,
                ];

                if ($lesson->relationLoaded('course')) {
                    $data['course'] = [
                        'id' => $lesson->course->id,
                        'title' => $lesson->course->title,
                    ];
                }

                return $data;
            }),
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'name' => $this->student->name,
                ];
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
