<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnswerQuestionRequest;
use App\Http\Resources\StudentAnswerResource;
use App\Models\ExamAttempt;
use App\Models\Question;

class StudentAnswerController extends Controller
{
    public function answer(AnswerQuestionRequest $request, Question $question)
    {
        $user = $request->user();
        $exam = $question->exam;

        if (! $exam->course->students()->where('student_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are not enrolled in this course.',
            ], 403);
        }

        $attempt = ExamAttempt::where('student_id', $user->id)
            ->where('exam_id', $exam->id)
            ->first();

        if (! $attempt) {
            return response()->json([
                'message' => 'You must start the exam before answering.',
            ], 400);
        }

        if ($exam->results()->where('student_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You have already submitted this exam.',
            ], 409);
        }

        if ($attempt->isExpired()) {
            return response()->json([
                'message' => 'Your exam time is up. You can no longer answer.',
            ], 400);
        }

        $isCorrect = strtolower($request->student_answer) === strtolower($question->correct_answer);

        $answer = $question->studentAnswers()->updateOrCreate(
            [
                'student_id' => $user->id,
            ],
            [
                'student_answer' => strtolower($request->student_answer),
                'is_correct' => $isCorrect,
            ]
        );

        return new StudentAnswerResource($answer);
    }
}
