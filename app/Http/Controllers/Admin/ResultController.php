<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResultResource;
use App\Models\ExamAttempt;
use App\Models\StudentResult;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    /**
     * كل نتايج الطلاب في السنتر — بفلاتر امتحان/طالب/كورس/حالة/درجة/تاريخ.
     */
    public function index(Request $request)
    {
        $query = StudentResult::query()->with(['student', 'exam.course'])->latest('submitted_at');

        $query->when($request->filled('exam_id'), fn ($q) => $q->where('exam_id', $request->query('exam_id')));
        $query->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->query('student_id')));
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

    /**
     * حذف نتيجة — يسمح للطالب بإعادة الامتحان.
     */
    public function destroy(StudentResult $result)
    {
        // نرجّع المحاولة مفتوحة تاني عشان الطالب يعيد من غير ما يبدأ من الأول
        ExamAttempt::where('student_id', $result->student_id)
            ->where('exam_id', $result->exam_id)
            ->update(['finished_at' => null]);

        $result->delete();

        return response()->json([
            'message' => 'Result deleted successfully. The student can retake the exam.',
        ]);
    }
}
