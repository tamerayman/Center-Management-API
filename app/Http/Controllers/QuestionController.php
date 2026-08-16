<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\Request;
use App\Http\Resources\QuestionResource;
class QuestionController extends Controller
{
    
      public function index(Request $request, Exam $exam){

        $user =$request->user();
        if($user->role==='teacher'){
        
          $questions=question::whereHas('exam',function($q)use($user,$exam){
            $q->where('id',$exam->id)
              ->whereHas('course',function($q)use($user){
                $q->where('teacher_id',$user->id);
              });
          })->get();
        }
        elseif($user->role==='student'){
          $questions=question::whereHas('exam',function($q)use($user,$exam){
            $q->where('id',$exam->id)
              ->whereHas('course.students',function($q)use($user){
                $q->where('student_id',$user->id);
              });
          })->get();
        }
        else{
          return response()->json(['message'=>'Unauthorized'],403);
        }
         $questions = $exam->questions()->get();
         return response()->json([
            'exam_title' => $questions,
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
