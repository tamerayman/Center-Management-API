<?php

namespace App\Http\Controllers;

use App\Http\Resources\StudentResultResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Notification;
use App\Models\StudentAnswer;
use App\Models\StudentResult;
use App\Services\GuardianNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentResultController extends Controller
{
    public function submit(Request $request, Exam $exam)
    {
        $user = $request->user();

        if (! $exam->course->students()->where('student_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are not enrolled in this course.',
            ], 403);
        }

        if (now()->lt($exam->start_time)) {
            return response()->json([
                'message' => 'Exam has not started yet.',
            ], 400);
        }

        if (now()->gt($exam->end_time)) {
            return response()->json([
                'message' => 'Exam has already ended.',
            ], 400);
        }

        $attempt = ExamAttempt::where('student_id', $user->id)
            ->where('exam_id', $exam->id)
            ->first();

        if (! $attempt) {
            return response()->json([
                'message' => 'You must start the exam before submitting.',
            ], 400);
        }

        if ($attempt->isExpired()) {
            return response()->json([
                'message' => 'Your exam time is up. You can no longer submit.',
            ], 400);
        }

        $answers = StudentAnswer::where('student_id', $user->id)
            ->whereHas('question', fn ($q) => $q->where('exam_id', $exam->id))
            ->with('question')
            ->get();

        $questionsCount = $exam->questions()->count();

        if ($answers->count() !== $questionsCount) {
            return response()->json([
                'message' => 'You must answer all questions before submitting.',
            ], 400);
        }

        try {
            $result = DB::transaction(function () use ($user, $exam, $answers, $attempt) {
                // فحص مزدوج تحت قفل لمنع التكرار
                $alreadySubmitted = StudentResult::where('student_id', $user->id)
                    ->where('exam_id', $exam->id)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadySubmitted) {
                    return null;
                }

                $exam->load('questions');
                $questionMap = $exam->questions->keyBy('id');

                $score = $answers->sum(function ($answer) use ($questionMap) {
                    $question = $questionMap->get($answer->question_id);
                    if (! $question) {
                        return 0;
                    }
                    $isCorrect = strtolower($answer->student_answer) === strtolower($question->correct_answer);
                    return $isCorrect ? (float) $question->marks : 0;
                });

                $totalMarks = $exam->total_marks;

                // pass_mark نسبة مئوية لكل امتحان
                $status = ($totalMarks > 0 && $score >= ($totalMarks * $exam->pass_mark / 100))
                    ? 'passed'
                    : 'failed';

                $result = StudentResult::create([
                    'student_id'   => $user->id,
                    'exam_id'      => $exam->id,
                    'score'        => $score,
                    'total_marks'  => $totalMarks,
                    'status'       => $status,
                    'submitted_at' => now(),
                ]);

                $attempt->update(['finished_at' => now()]);

                return $result;
            });
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                return response()->json([
                    'message' => 'You have already submitted this exam.',
                ], 409);
            }
            throw $e;
        }

        if ($result === null) {
            return response()->json([
                'message' => 'You have already submitted this exam.',
            ], 409);
        }

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Exam Result',
            'message' => "Your result for '{$exam->title}' is now available.",
        ]);

        // إشعار ولي الأمر بنتيجة جديدة
        app(GuardianNotifier::class)->notifyGuardians(
            $user,
            'Exam Result',
            "{$user->name} received a new exam result for '{$exam->title}': {$result->score}/{$result->total_marks} ({$result->status})."
        );

        $result->load(['student', 'exam']);

        return response()->json([
            'message' => 'Exam submitted successfully.',
            'result' => new StudentResultResource($result),
        ]);
    }

    public function myResults(Request $request)
    {
        $results = $request->user()
            ->results()
            ->with(['student', 'exam'])
            ->latest('submitted_at')
            ->paginate($this->perPage($request));

        return StudentResultResource::collection($results);
    }

    /**
     * النتايج — role-scoped:
     * student → نتايجه فقط، teacher → كورساته فقط، admin/secretary → الكل.
     * فلاتر: student_id / exam_id / course_id / status / min_score / max_score / date_from / date_to.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = StudentResult::query()->with(['student', 'exam.course'])->latest('submitted_at');

        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isTeacher()) {
            $query->whereHas('exam.course', fn ($q) => $q->where('teacher_id', $user->id));
        }

        $query->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->query('student_id')));
        $query->when($request->filled('exam_id'), fn ($q) => $q->where('exam_id', $request->query('exam_id')));
        $query->when($request->filled('course_id'), fn ($q) => $q->whereHas(
            'exam',
            fn ($e) => $e->where('course_id', $request->query('course_id'))
        ));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));
        $query->when($request->filled('min_score'), fn ($q) => $q->where('score', '>=', (int) $request->query('min_score')));
        $query->when($request->filled('max_score'), fn ($q) => $q->where('score', '<=', (int) $request->query('max_score')));
        $query->when($request->filled('date_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->query('date_from')));
        $query->when($request->filled('date_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->query('date_to')));

        return StudentResultResource::collection($query->paginate($this->perPage($request, 15)));
    }

    public function examResults(Request $request, Exam $exam)
    {
        $this->authorize('viewResults', [StudentResult::class, $exam]);

        $results = $exam->results()->with('student')->paginate($this->perPage($request, 15));

        return StudentResultResource::collection($results);
    }
}
