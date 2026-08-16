<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Http\Resources\CourseResource;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use GuzzleHttp\Psr7\Response;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::with('teacher')->get();
        return CourseResource::collection($courses);
    }

    public function myCourses(Request $request)
    {
        if ($request->user()->role == 'teacher') {

            $courses = $request->user()->courses()->get();
        } else {

            $courses = $request->user()->enrolledCourses()->with('teacher')->get();
        }

        return CourseResource::collection($courses);
    }

    public function myCourseDetails(Request $request,  Course $course)
    {
        if ($request->user()->role === 'teacher') {
            $course = $request->user()->courses()->with(['teacher', 'lessons', 'exams'])->findOrFail($course->id);
        } elseif ($request->user()->role == 'student') {

            $course = $request->user()->enrolledCourses()->with(['teacher', 'lessons', 'exams'])->findOrFail($course->id);
        } 
        else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new CourseResource($course);
    }

    public function store(StoreCourseRequest $request)
    {
      
        $course = $request->user()->courses()->create($request->validated());

        return new CourseResource($course);
    }

    public function show(Course $course)
    {
        $course = Course::with(['teacher', 'lessons'])->findOrFail($course->id);


        return new CourseResource($course);
    }


    public function update(UpdateCourseRequest $request, Course $course)
    {

         $this->authorize('update', $course);
     
        $course->update($request->validated());

        return new CourseResource($course);
    }


    public function destroy(Request $request, Course $course)
    {
        $this->authorize('delete', $course);
        // $course = $request->user()->courses()->findOrFail($id);

        $course->delete();
        return response()->json([
            'message' => 'Course deleted successfully',
        ]);
    }

    public function join(Request $request, Course $course)
    {


        if ($course->students()->where('student_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'You are already enrolled in this course.'
            ], 409);
        }
        $course->students()->syncWithoutDetaching($request->user()->id);
       $request->user->enrolledCourses()->syncWithoutDetaching($course->id);
        return response()->json([
            'message' => 'Course joined successfully.'
        ]);
    }

    public function leave(Request $request, Course $course)
    {


        $course->students()->detach($request->user()->id);

        return response()->json([
            'message' => 'Course left successfully.'
        ]);
    }

     public function students(Course $course)
     {

        $students = $course->students()->get();

        return response()->json([
            'students' => $students
        ]);
     }
}
