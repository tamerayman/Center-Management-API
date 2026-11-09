<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkChildRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\GuardianChildResource;
use App\Http\Resources\LessonResource;
use App\Models\GuardianStudent;
use App\Models\Lesson;
use App\Models\Notification;
use App\Models\User;
use App\Policies\GuardianPolicy;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class ChildController extends Controller
{
    /**
     * My Children — مع summary بسيطة لكل طفل.
     */
    public function index(Request $request)
    {
        $guardian = $request->user('guardian');

        $progressService = app(ProgressService::class);

        $children = $guardian->children()->get()->map(function ($student) use ($guardian, $progressService) {
            return [
                'id' => $student->id,
                'name' => $student->name,
                'relationship' => $student->guardians->firstWhere('id', $guardian->id)?->pivot?->relationship,
                'courses_count' => $student->enrolledCourses()->count(),
                'attendance_rate' => $progressService->attendancePercentageFor($student) ?? 0,
                'unread_notifications' => $guardian->notifications()
                    ->unread()->where('student_id', $student->id)->count(),
            ];
        });

        return response()->json(['data' => $children]);
    }

    /**
     * ربط طفل جديد بكود ولي الأمر — من غير إنشاء حساب جديد.
     */
    public function store(LinkChildRequest $request)
    {
        $guardian = $request->user('guardian');

        $student = User::where('guardian_code', $request->guardian_code)
            ->where('role', 'student')
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'The guardian code is invalid.',
                'errors' => ['guardian_code' => ['The guardian code is invalid.']],
            ], 422);
        }

        // GuardianPolicy: المكرر → الـ policy يمنع والـ controller يرد 409 برسالة أوضح
        if ($guardian->hasChild($student)) {
            return response()->json([
                'message' => 'This student is already linked to your account.',
            ], 409);
        }

        GuardianStudent::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => $request->relationship,
        ]);

        // إشعار للطالب إن ولي أمره اتضاف
        Notification::create([
            'user_id' => $student->id,
            'title' => 'Guardian Linked',
            'message' => "Your guardian {$guardian->name} ({$request->relationship}) has been linked to your account.",
        ]);

        return response()->json([
            'message' => 'Child linked successfully.',
            'child' => [
                'id' => $student->id,
                'name' => $student->name,
                'relationship' => $request->relationship,
            ],
        ], 201);
    }

    /**
     * الملف الكامل لطفل — Read-Only — 403 لأي طالب مش مربوط بيه.
     */
    public function show(Request $request, User $student)
    {
        $guardian = $request->user('guardian');

        if (! app(GuardianPolicy::class)->viewChild($guardian, $student)) {
            return response()->json([
                'message' => 'This student is not linked to your account.',
            ], 403);
        }

        return response()->json([
            'data' => (new GuardianChildResource($student))->toArray($request),
        ]);
    }

    /**
     * تقدم الطفل — محسوب من نفس خدمة التقدم — Read-Only.
     */
    public function progress(Request $request, User $student)
    {
        $guardian = $request->user('guardian');

        if (! app(GuardianPolicy::class)->viewChild($guardian, $student)) {
            return response()->json([
                'message' => 'This student is not linked to your account.',
            ], 403);
        }

        $courseId = $request->filled('course_id') ? (int) $request->query('course_id') : null;
        $progressService = app(ProgressService::class);

        return response()->json([
            'student' => ['id' => $student->id, 'name' => $student->name],
            'overall' => $progressService->forStudent($student, $courseId),
            'per_course' => $courseId ? null : $progressService->perCourse($student),
        ]);
    }

    /**
     * الدروس القادمة للطفل — Read-Only — بفلاتر course_id/date_from/date_to.
     */
    public function upcomingLessons(Request $request, User $student)
    {
        $guardian = $request->user('guardian');

        if (! app(GuardianPolicy::class)->viewChild($guardian, $student)) {
            return response()->json([
                'message' => 'This student is not linked to your account.',
            ], 403);
        }

        $lessons = Lesson::query()->with('course.teacher')->upcoming()
            ->whereHas('course.students', fn ($q) => $q->where('student_id', $student->id))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->query('course_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('lesson_date', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('lesson_date', '<=', $request->query('date_to')))
            ->orderBy('lesson_date')
            ->paginate($this->perPage($request));

        return LessonResource::collection($lessons);
    }

    /**
     * سجل حضور الطفل — paginated — Read-Only — بفلاتر status/course_id/date.
     */
    public function attendance(Request $request, User $student)
    {
        $guardian = $request->user('guardian');

        if (! app(GuardianPolicy::class)->viewChild($guardian, $student)) {
            return response()->json([
                'message' => 'This student is not linked to your account.',
            ], 403);
        }

        $attendance = $student->attendances()->with('lesson.course')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('course_id'), fn ($q) => $q->whereHas(
                'lesson',
                fn ($l) => $l->where('course_id', $request->query('course_id'))
            ))
            ->when($request->filled('date_from'), fn ($q) => $q->whereHas(
                'lesson',
                fn ($l) => $l->whereDate('lesson_date', '>=', $request->query('date_from'))
            ))
            ->when($request->filled('date_to'), fn ($q) => $q->whereHas(
                'lesson',
                fn ($l) => $l->whereDate('lesson_date', '<=', $request->query('date_to'))
            ))
            ->latest()
            ->paginate($this->perPage($request, 15));

        return AttendanceResource::collection($attendance);
    }

    /**
     * سجل مدفوعات الطفل — paginated — Read-Only.
     */
    public function payments(Request $request, User $student)
    {
        $guardian = $request->user('guardian');

        if (! app(GuardianPolicy::class)->viewChild($guardian, $student)) {
            return response()->json([
                'message' => 'This student is not linked to your account.',
            ], 403);
        }

        $payments = $student->payments()
            ->with('course')
            ->latest('paid_at')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => collect($payments->items())->map(fn ($payment) => [
                'id' => $payment->id,
                'course' => $payment->course?->title,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at,
                'method' => $payment->method,
                'status' => $payment->status,
            ]),
            'links' => $payments->linkCollection(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }
}
