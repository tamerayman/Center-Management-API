<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Lesson;
use App\Models\User;
use App\Services\GuardianNotifier;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function store(StoreAttendanceRequest $request, Lesson $lesson)
    {
        $this->authorize('create', [Attendance::class, $lesson]);

        $validated = $request->validated();
        $notifier = app(GuardianNotifier::class);

        foreach ($validated['students'] as $student) {
            Attendance::updateOrCreate(
                [
                    'student_id' => $student['student_id'],
                    'lesson_id' => $lesson->id,
                ],
                [
                    'status' => $student['status'],
                ]
            );

            // إشعار ولي الأمر بحالة الحضور
            $studentUser = User::find($student['student_id']);

            if ($studentUser) {
                $statusText = match ($student['status']) {
                    'present' => 'attended',
                    'late' => 'was late for',
                    default => 'was absent from',
                };

                $notifier->notifyGuardians(
                    $studentUser,
                    'Attendance',
                    "{$studentUser->name} {$statusText} today's lesson '{$lesson->title}'."
                );
            }
        }

        return response()->json([
            'message' => 'Attendance saved successfully.',
        ]);
    }

    /**
     * سجل الحضور — role-scoped:
     * student → سجله فقط، teacher → كورساته فقط، admin/secretary → الكل.
     * فلاتر: student_id / course_id / lesson_id / status / date_from / date_to (على lesson_date).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Attendance::query()->with(['student', 'lesson.course'])->latest();

        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isTeacher()) {
            $query->whereHas('lesson.course', fn ($q) => $q->where('teacher_id', $user->id));
        }

        $query->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->query('student_id')));
        $query->when($request->filled('lesson_id'), fn ($q) => $q->where('lesson_id', $request->query('lesson_id')));
        $query->when($request->filled('course_id'), fn ($q) => $q->whereHas(
            'lesson',
            fn ($l) => $l->where('course_id', $request->query('course_id'))
        ));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));
        $query->when($request->filled('date_from'), fn ($q) => $q->whereHas(
            'lesson',
            fn ($l) => $l->whereDate('lesson_date', '>=', $request->query('date_from'))
        ));
        $query->when($request->filled('date_to'), fn ($q) => $q->whereHas(
            'lesson',
            fn ($l) => $l->whereDate('lesson_date', '<=', $request->query('date_to'))
        ));

        return AttendanceResource::collection($query->paginate($this->perPage($request, 15)));
    }

    public function lessonAttendance(Request $request, Lesson $lesson)
    {
        $this->authorize('viewAttendance', [Attendance::class, $lesson]);

        $attendance = $lesson->attendances()->with('student')->paginate($this->perPage($request, 20));

        return AttendanceResource::collection($attendance);
    }

    public function myAttendance(Request $request)
    {
        $attendance = $request->user()
            ->attendances()
            ->with('lesson.course')
            ->latest()
            ->paginate($this->perPage($request));

        return AttendanceResource::collection($attendance);
    }
}
