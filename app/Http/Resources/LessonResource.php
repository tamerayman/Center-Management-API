<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
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
        'title' => $this->title,
        'description' => $this->description,
        'lesson_date' => $this->lesson_date,

     "course" => new CourseResource($this->whenLoaded('course')),

    ];
    }
}
