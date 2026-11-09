<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminStoreCourseRequest;
use App\Http\Resources\UserResource;
use App\Models\Course;
use App\Models\User;
use App\Services\Finance\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * الأدمن ينشئ كورس لأي مدرس — مش نفسه بس.
     */
    public function store(AdminStoreCourseRequest $request): JsonResponse
    {
        $course = Course::create($request->validated());

        return response()->json([
            'message' => 'Course created successfully.',
            'data' => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'teacher_id' => $course->teacher_id,
            ],
        ], 201);
    }

    /**
     * تسجيل طلاب في الكورس بالقوة.
     */
    public function attachStudents(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $invalid = User::whereIn('id', $validated['student_ids'])
            ->where('role', '!=', 'student')
            ->count();

        if ($invalid > 0) {
            return response()->json([
                'message' => 'All student_ids must belong to students.',
            ], 422);
        }

        $course->students()->syncWithoutDetaching($validated['student_ids']);

        // الاشتراك المالي بيتبني تلقائياً مع التسجيل بالقوة (لو الكورس له سعر معلن)
        $subscriptionService = app(SubscriptionService::class);

        $subscriptionsCreated = collect($validated['student_ids'])
            ->filter(fn ($id) => ! $course->subscriptions()->where('student_id', $id)->active()->exists())
            ->map(fn ($id) => $subscriptionService->createForStudent(
                User::find($id),
                $course,
                $request->user()->id,
            ))
            ->filter()
            ->count();

        return response()->json([
            'message' => 'Students enrolled successfully.',
            'students_count' => $course->students()->count(),
            'subscriptions_created' => $subscriptionsCreated,
        ]);
    }

    /**
     * إخراج طالب من الكورس بالقوة.
     */
    public function detachStudent(Course $course, User $student): JsonResponse
    {
        if (! $course->students()->where('student_id', $student->id)->exists()) {
            return response()->json([
                'message' => 'This student is not enrolled in this course.',
            ], 404);
        }

        $course->students()->detach($student->id);

        // إغلاق الاشتراك المالي — التاريخ بيفضل موجود
        app(SubscriptionService::class)
            ->cancelActiveFor($student->id, $course->id);

        return response()->json([
            'message' => 'Student removed from course successfully.',
        ]);
    }

    /**
     * طلاب الكورس.
     */
    public function students(Request $request, Course $course)
    {
        $students = $course->students()->paginate($this->perPage($request, 15));

        return UserResource::collection($students);
    }
}
