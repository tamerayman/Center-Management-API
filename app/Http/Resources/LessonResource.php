<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'lesson_date' => $this->lesson_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'location' => $this->location,
            'delivery_type' => $this->delivery_type, // onsite, online
            'status' => $this->lesson_date >= now() ? 'upcoming' : 'past',

            'course' => $this->whenLoaded('course', fn () => new CourseResource($this->course)),
        ];
    }
}
