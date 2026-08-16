<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Http\Requests\AnswerQuestionRequest;
use App\Http\Resources\StudentAnswerResource;

class StudentAnswerController extends Controller
{
    public function answer(AnswerQuestionRequest $request, Question $question)
    {
        $user = $request->user();
        if ($user->role !== 'student') {
            return response()->json([
                'message' => 'Only students can answer questions.'
            ], 403);
        }
        if (!$question->exam->course->students()->where('student_id', $user->id)->exists()) {

            return response()->json([
                'message' => 'You are not enrolled in this course.'
            ], 403);
        }
        $exam = $question->exam;

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
        // if (!$exam->questions()->exists()) {
        //     return response()->json([
        //         'message' => 'This exam has no questions.'
        //     ], 400);
        // }
        
        // if (StudentAnswer::where('student_id', $user->id)->where('question_id', $question->id)->exists()) {
        //     return response()->json([
        //         'message' => 'You already answered this question.'
        //     ], 409);
        // }
        $isCorrect = strtolower($request->student_answer) === strtolower($question->correct_answer);

        $answer = StudentAnswer::updateOrCreate(
            [
                'student_id' => $user->id,
                'question_id' => $question->id,
            ],
            [
                'student_answer' => $request->student_answer,
                'is_correct' => $isCorrect,
            ]
        );

        return new StudentAnswerResource($answer);
    }
}
