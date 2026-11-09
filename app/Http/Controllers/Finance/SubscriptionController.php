<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\User;
use App\Services\Finance\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    /**
     * كل الاشتراكات — admin/secretary يشوفوا الكل، والمدرس كورساته فقط.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = CourseSubscription::query()->with(['student', 'course'])->latest();

        if ($user->isTeacher()) {
            $query->whereHas('course', fn ($q) => $q->where('teacher_id', $user->id));
        } else {
            $this->authorize('finance.viewAny');
        }

        $query->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->query('student_id')));
        $query->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->query('course_id')));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));

        return SubscriptionResource::collection($query->paginate($this->perPage($request)));
    }

    /**
     * إنشاء اشتراك يدوي — للأدمن. بيسجل الطالب في الكورس كمان لو مش مسجل.
     */
    public function store(StoreSubscriptionRequest $request)
    {
        $this->authorize('finance.sensitive');

        $student = User::findOrFail($request->student_id);
        $course = Course::findOrFail($request->course_id);

        $subscription = $this->subscriptions->createForStudent(
            $student,
            $course,
            $request->user()->id,
            $request->start_date,
            $request->monthly_fee !== null ? (float) $request->monthly_fee : null,
            $request->monthly_discount !== null ? (float) $request->monthly_discount : null,
            $request->discount_reason,
        );

        if (! $subscription) {
            return response()->json([
                'message' => $course->currentFee() === null && $request->monthly_fee === null
                    ? 'This course has no declared fee — set a course fee or pass monthly_fee explicitly.'
                    : 'This student already has an active subscription for this course.',
            ], 409);
        }

        // الاشتراك المالي لازم يكمل بوصول المحتوى (pivot)
        $course->students()->syncWithoutDetaching($student->id);

        return response()->json([
            'message' => 'Subscription created successfully.',
            'data' => new SubscriptionResource($subscription->load(['student', 'course'])),
        ], 201);
    }

    /**
     * تعديل اشتراك: pause/resume/cancel/complete + تعديل الخصم — للأدمن.
     */
    public function update(UpdateSubscriptionRequest $request, CourseSubscription $subscription)
    {
        $this->authorize('finance.sensitive');

        $data = collect($request->validated())->except(['status'])->all();

        if (isset($data['monthly_fee']) || isset($data['monthly_discount'])) {
            $data['monthly_fee'] = isset($data['monthly_fee'])
                ? number_format((float) $data['monthly_fee'], 2, '.', '')
                : $subscription->monthly_fee;
            $data['monthly_discount'] = isset($data['monthly_discount'])
                ? number_format((float) $data['monthly_discount'], 2, '.', '')
                : $subscription->monthly_discount;
        }

        if ($request->filled('status')) {
            $data['status'] = $request->status;

            if (in_array($request->status, ['cancelled', 'completed']) && ! $subscription->end_date) {
                $data['end_date'] = $request->end_date ?? today()->toDateString();
            }
        }

        $subscription->update($data);

        return response()->json([
            'message' => 'Subscription updated successfully.',
            'data' => new SubscriptionResource($subscription->fresh()->load(['student', 'course'])),
        ]);
    }
}
