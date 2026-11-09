<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(private ProgressService $progress) {}

    /**
     * تقدم الطالب نفسه — كلي + لكل كورس.
     */
    public function myProgress(Request $request)
    {
        $user = $request->user();

        if (! $user->isStudent()) {
            return response()->json([
                'message' => 'Only students have a progress report.',
            ], 403);
        }

        $courseId = $request->filled('course_id') ? (int) $request->query('course_id') : null;

        return response()->json([
            'student' => ['id' => $user->id, 'name' => $user->name],
            'overall' => $this->progress->forStudent($user, $courseId),
            'per_course' => $courseId ? null : $this->progress->perCourse($user),
        ]);
    }

    /**
     * تقدم طالب معين — admin/secretary للكل، teacher لطلاب كورساته فقط.
     */
    public function studentProgress(Request $request, User $student)
    {
        $viewer = $request->user();

        $allowed = $viewer->isFinancialStaff()
            || ($viewer->isTeacher() && $student->enrolledCourses()->where('teacher_id', $viewer->id)->exists());

        if (! $allowed) {
            return response()->json([
                'message' => 'You are not allowed to view this student progress.',
            ], 403);
        }

        $courseId = $request->filled('course_id') ? (int) $request->query('course_id') : null;

        return response()->json([
            'student' => ['id' => $student->id, 'name' => $student->name],
            'overall' => $this->progress->forStudent($student, $courseId),
            'per_course' => $courseId ? null : $this->progress->perCourse($student),
        ]);
    }
}
