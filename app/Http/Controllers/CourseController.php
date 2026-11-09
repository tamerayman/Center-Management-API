<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Http\Resources\UserResource;
use App\Models\Course;
use App\Models\Notification;
use App\Services\Finance\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        // كتالوج الكورسات متاح لكل مستخدم مسجل — الفلاتر مش بتتجاوز أي صلاحية
        $courses = Course::query()
            ->with('teacher')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(fn ($w) => $w
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"));
            })
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->query('teacher_id')))
            ->latest()
            ->paginate($this->perPage($request));

        return CourseResource::collection($courses);
    }

    public function myCourses(Request $request)
    {
        $user = $request->user();

        if ($user->isTeacher()) {
            $courses = $user->courses()->latest()->paginate($this->perPage($request));
        } elseif ($user->isStudent()) {
            $courses = $user->enrolledCourses()->with('teacher')->latest()->paginate($this->perPage($request));
        } else {
            // admin / secretary — يشوفوا كل الكورسات
            abort_unless(
                in_array($user->role, ['admin', 'secretary']),
                403,
                'Access denied.'
            );
            $courses = Course::with('teacher')->latest()->paginate($this->perPage($request));
        }

        return CourseResource::collection($courses);
    }

    public function myCourseDetails(Request $request, Course $course)
    {
        $user = $request->user();

        if ($user->isTeacher()) {
            $course = $user->courses()->with(['teacher', 'lessons', 'exams'])->findOrFail($course->id);
        } elseif ($user->isStudent()) {
            $course = $user->enrolledCourses()->with(['teacher', 'lessons', 'exams'])->findOrFail($course->id);
        } else {
            $course = Course::with(['teacher', 'lessons', 'exams'])->findOrFail($course->id);
        }

        return new CourseResource($course);
    }

    public function store(StoreCourseRequest $request)
    {
        $this->authorize('create', Course::class);

        $course = $request->user()->courses()->create($request->validated());

        return new CourseResource($course);
    }

    public function show(Course $course)
    {
        $course->load(['teacher', 'lessons']);

        return new CourseResource($course);
    }

    public function update(UpdateCourseRequest $request, Course $course)
    {
        $this->authorize('update', $course);

        $course->update($request->validated());

        return new CourseResource($course);
    }

    public function destroy(Request $request, Course $course)
    {
        $this->authorize('delete', $course);

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully',
        ]);
    }

    public function join(Request $request, Course $course)
    {
        if ($course->students()->where('student_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'You are already enrolled in this course.'
            ], 409);
        }

        $subscription = DB::transaction(function () use ($request, $course) {
            $course->students()->attach($request->user()->id);

            // كورس له سعر معلن → الاشتراك المالي بيتفتح تلقائياً بالسعر الحالي
            return app(SubscriptionService::class)
                ->createForStudent($request->user(), $course, $request->user()->id);
        });

        Notification::create([
            'user_id' => $request->user()->id,
            'title'   => 'Course Enrollment',
            'message' => "You have been enrolled in {$course->title}."
                . ($subscription ? ' Monthly fee: ' . $subscription->final_monthly_amount . ' EGP.' : ''),
        ]);

        // إشعار ولي الأمر بانضمام الطالب لكورس جديد
        app(\App\Services\GuardianNotifier::class)->notifyGuardians(
            $request->user(),
            'Course Enrollment',
            "{$request->user()->name} was enrolled in a new course: {$course->title}."
        );

        return response()->json([
            'message' => 'Course joined successfully.',
            'subscription' => $subscription ? [
                'monthly_fee' => $subscription->monthly_fee,
                'monthly_discount' => $subscription->monthly_discount,
                'final_monthly_amount' => $subscription->final_monthly_amount,
            ] : null,
        ]);
    }

    public function leave(Request $request, Course $course)
    {
        if (! $course->students()->where('student_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'You are not enrolled in this course.'
            ], 404);
        }

        DB::transaction(function () use ($request, $course) {
            $course->students()->detach($request->user()->id);

            // إغلاق الاشتراك المالي — التاريخ القديم بيفضل موجود
            app(SubscriptionService::class)->cancelActiveFor($request->user()->id, $course->id);
        });

        Notification::create([
            'user_id' => $request->user()->id,
            'title'   => 'Course Unenrollment',
            'message' => "You have been unenrolled from {$course->title}.",
        ]);

        return response()->json([
            'message' => 'Course left successfully.'
        ]);
    }

    public function students(Request $request, Course $course)
    {
        $this->authorize('update', $course); // فقط المدرس المالك أو الأدمن

        $students = $course->students()->paginate($this->perPage($request, 15));

        return UserResource::collection($students);
    }
}
