<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExamRequest;
use Illuminate\Http\Request;
use App\Models\Exam;
use App\Models\Course;
use App\Http\Resources\ExamResource;


class ExamController extends Controller
{
    public function myExams(Request $request)
    {

        $user = $request->user();
        if ($user->role === 'teacher') {
            $exams = $user->courses()->with('exams')->get()->pluck('exams')->flatten();
            // او
            //   $exams=Exam::whereHas('course',function($q)use($user){
            //     $q->where('teacher_id',$user->id);
            //   });

        } elseif ($user->role === 'student') {
            $exams = $user->enrolledCourses()->with('exams')->get()->pluck('exams')->flatten();
            // او
            // $exams=Exam::whereHas('course.students',function($q)use($user){
            //     $q->where('student_id',$user->id);
            //   });
        }
        return  ExamResource::collection($exams);
    }

    public function myExamDetails(Request $request, Exam $exam)
    {
        $user = $request->user();
        if ($user->role === 'teacher') {
            $exam = Exam::with(['course', 'course.teacher'])
                ->whereHas('course', function ($q) use ($user) {
                    $q->where('teacher_id', $user->id);
                })->findOrFail($exam->id);
        } elseif ($user->role === 'student') {
            $exam = Exam::with(['course', 'course.teacher'])
                ->whereHas('course.students', function ($q) use ($user) {
                    $q->where('student_id', $user->id);
                })->findOrFail($exam->id);
        }
        return new ExamResource($exam);
    }

    public function store(StoreExamRequest $request, Course $course)
    {

        $this->authorize('create', [Exam::class, $course]);

        $exam = $course->exams()->create($request->validated());
        return new ExamResource($exam);
    }

    public function update(StoreExamRequest $request, Exam $exam)
    {
        $this->authorize('update', $exam);

        $exam->update($request->validated());
        return new ExamResource($exam);
    }

    public function destroy(Request $request, Exam $exam)
    {
        $this->authorize('delete', $exam);
        $exam->delete();
        return response()->json(['message' => 'Exam deleted successfully']);
    }

}
