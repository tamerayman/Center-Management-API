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
        ];
    }
}
