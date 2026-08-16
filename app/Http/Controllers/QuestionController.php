<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\Request;
use App\Http\Resources\QuestionResource;

class QuestionController extends Controller
{

  public function index(Request $request, Exam $exam)
  { 
    $this->authorize('viewQuestions', [Question::class, $exam]);

      $questions = $exam->questions()->get();

    //or
    // $user = $request->user();
    // if ($user->role === 'teacher') {

    //   $questions = Question::where('exam_id', $exam->id)
    //     ->whereHas('exam.course', function ($q) use ($user) {
    //       $q->where('teacher_id', $user->id);
    //     })->get();

      //or 
      //  $request->user()->courses()->with(['exams.questions'])->get()->pluck('exams')->flatten()
      //  ->pluck('questions')->flatten();
      //-----------------------------------------------------------
    // } elseif ($user->role === 'student') {
    //   $questions = question::where('exam_id', $exam->id)
    //     ->whereHas('exam.course.students', function ($q) use ($user) {
    //       $q->where('student_id', $user->id);
    //     })->get();
      //or
      //$user->enrollcourses()->with(['exams.questions'])->get()->pluck('exams')->flatten()
      //->pluck('questions')->flatten();
    // } else {
    //   return response()->json(['message' => 'Unauthorized'], 403);
    // }

    return response()->json([
      'exam' => [
        'id' => $exam->id,
        'title' => $exam->title,
      ],
      'questions' => QuestionResource::collection($questions),
    ]);
  }
  public function store(StoreQuestionRequest $request, Exam $exam)
  {
    $this->authorize('create', [Question::class, $exam]);

    $question = $exam->questions()->create($request->validated());

    return new QuestionResource($question);
  }

  public function update(StoreQuestionRequest $request, Question $question)
  {
    $this->authorize('update', $question);

    $question->update($request->validated());

    return new QuestionResource($question);
  }


  public function destroy(Question $question)
  {
    $this->authorize('delete', $question);

    $question->delete();

    return response()->json(['message' => 'Question deleted successfully']);
  }
}
