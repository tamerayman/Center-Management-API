<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this['user']->id,
                'name' => $this['user']->name,
                'email' => $this['user']->email,
                'role' => $this['user']->role,
            ],

            'statistics' => $this['statistics'],

            'courses' => CourseResource::collection($this['courses']),

            'upcoming_exams' => ExamResource::collection($this['upcoming_exams']),

            'recent_results' => $this->when(
                isset($this['recent_results']),
                function () {
                    return StudentResultResource::collection(
                        $this['recent_results']
                    );
                }
            ),

            'recent_materials' => $this->when(
                isset($this['recent_materials']),
                function () {
                    return LessonMaterialResource::collection(
                        $this['recent_materials']
                    );
                }
            ),

            'upcoming_lessons' => $this->when(
                isset($this['upcoming_lessons']),
                fn () => LessonResource::collection($this['upcoming_lessons'])
            ),

            'progress' => $this->when(isset($this['progress']), fn () => $this['progress']),

            'progress_per_course' => $this->when(isset($this['progress_per_course']), fn () => $this['progress_per_course']),

            'course_insights' => $this->when(isset($this['course_insights']), fn () => $this['course_insights']),
        ];
    }
}
