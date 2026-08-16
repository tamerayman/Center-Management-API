<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;
use App\Models\StudentResult;
use App\Models\StudentAnswer;
use App\Models\Notification;
use App\Http\Resources\StudentResultResource;

class StudentResultController extends Controller
{
    public function submit(Request $request, Exam $exam)
    {
        $user = $request->user();
        if ($user->role !== 'student') {
            return response()->json([
                'message' => 'Only students can submit exams.'
            ], 403);
        }


        if (!$exam->course->students()->where('student_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are not enrolled in this course.'
            ], 403);
        }
        $now = now();

        if ($now->lt($exam->start_time)) {
            return response()->json([
                'message' => 'Exam has not started yet.'
            ], 400);
        }

        if ($now->gt($exam->end_time)) {
            return response()->json([
                'message' => 'Exam has already ended.'
            ], 400);
        }
        if (StudentResult::where('student_id', $user->id)->where('exam_id', $exam->id)->exists()) {
            return response()->json([
                'message' => 'You have already submitted this exam.'
            ], 409);
        }

        $answers = StudentAnswer::where('student_id', $user->id)
            ->whereHas('question', function ($q) use ($exam) {
                $q->where('exam_id', $exam->id);
            })
            ->get();
        //or $answers = $exam->questions()
        // ->with(['answers' => function($q) use ($user) {
        //     $q->where('student_id', $user->id);
        // }])
        // ->get()
        // ->pluck('answers')
        // ->flatten();

        $questionsCount = $exam->questions()->count();

        if ($answers->count() != $questionsCount) {
            return response()->json([
                'message' => 'You must answer all questions before submitting.'
            ], 400);
        }


        $score = $answers->sum(function ($answer) {
            return $answer->is_correct
                ? $answer->question->marks
                : 0;
        });

        $totalMarks = $exam->total_marks;
        //or  $totalMarks = $exam->questions()->sum('marks');
        $status = $score >= ($totalMarks / 2)
            ? 'passed'
            : 'failed';


        $result = StudentResult::create([
            'student_id' => $user->id,
            'exam_id' => $exam->id,
            'score' => $score,
            'total_marks' => $totalMarks,
            'status' => $status,
            'submitted_at' => now(),
        ]);
     // Create a notification for the student about the result
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Exam Result',
            'message' => "Your result for '{$exam->title}' is now available.",
        ]);


        $result->load(['student', 'exam']);

        return response()->json([
            'message' => 'Exam submitted successfully.',
            'result' => new StudentResultResource($result),
        ]);
    }

    public function myResults(Request $request)
    {
        $user = $request->user();
        //or 
        // $results = StudentResult::where('student_id', $user->id)
        //     ->with('exam.course')
        //     ->get();
        $results = $user->results()->with(['student', 'exam'])->get();

        return StudentResultResource::collection($results);
    }

    public function examResults(Exam $exam)
    {

        $this->authorize('viewResults', [StudentResult::class, $exam]);
        //or
        // $results = StudentResult::where('exam_id', $exam->id)
        //     ->with(['student', 'exam'])
        //     ->get();
        $results = $exam->results()->with('student')->get();

        return StudentResultResource::collection($results);
    }
}
