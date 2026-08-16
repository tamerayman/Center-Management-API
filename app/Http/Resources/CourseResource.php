<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ExamResource;

class CourseResource extends JsonResource
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
            'teacher_id'=>$this->teacher_id,

            // 'teacher' => new UserResource($this->whenLoaded('teacher')),
            'teacher' => $this->whenLoaded('teacher', fn() => new UserResource($this->teacher)),  

            'lessons' => LessonResource::collection(
                $this->whenLoaded('lessons')
            ),
         'exams' => ExamResource::collection(
                $this->whenLoaded('exams')
            ),
               'students' => UserResource::collection(
            $this->whenLoaded('students')
        ),
        ];
    }
}
