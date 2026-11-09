<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Request $request, Exam $exam)
    {
        $this->authorize('viewQuestions', [Question::class, $exam]);

        $questions = $exam->questions()->paginate($this->perPage($request));

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
            ],
            'questions' => QuestionResource::collection($questions)->response()->getData(true),
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
