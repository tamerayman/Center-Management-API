<?php

namespace App\Http\Controllers;

use App\Http\Resources\LessonResource;
use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\Course;
use App\Http\Requests\StoreLessonRequest;

class LessonController extends Controller
{
    public function mylessons(Request $request)
    {

        if ($request->user()->role === 'teacher') {
            $lessons = $request->user()->courses()->with('lessons')->get()->pluck('lessons')->flatten();
        } elseif ($request->user()->role === 'student') {
            $lessons = $request->user()->enrolledCourses()->with('lessons')->get()->pluck('lessons')->flatten();
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return LessonResource::collection($lessons);
    }

    //  public function mylessonsDetails(Request $request,  Lesson $lesson)
    //  {
    //     if ($request->user()->role==='teacher'){
    //         $lesson=$request->user()->courses()->with(['lessons','course'])->findOrFail($lesson->id);
    //     }
    //     elseif ($request->user()->role==='student'){
    //         $lesson=$request->user()->enrolledCourses()->with(['lessons','course'])->findOrFail($lesson->id);
    //     }
    //     else{
    //         return response()->json(['message' => 'Unauthorized'], 403);
    //     }

    //     return new LessonResource($lesson);
    //  }
    public function mylessonsDetails(Request $request, Lesson $lesson)
    {
        if ($request->user()->role === 'teacher') {
            $lesson = Lesson::with(['course', 'course.teacher'])
                ->whereHas('course', function ($q) use ($request) {
                    $q->where('teacher_id', $request->user()->id);
                })->findOrFail($lesson->id);
        } elseif ($request->user()->role === 'student') {
            $lesson = Lesson::with(['course', 'course.teacher'])
                ->whereHas('course.students', function ($q) use ($request) {
                    $q->where('student_id', $request->user()->id);
                })->findOrFail($lesson->id);
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new LessonResource($lesson);
    }
    
    public function store(StoreLessonRequest $request, Course $course)
    {

        $lesson = $course->lessons()->create($request->validated());

        return new LessonResource($lesson);
    }
}
