<?php

namespace App\Services\Finance;

use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    /**
     * فتح اشتراك مالي active لطالب في كورس — مع منع الازدواج بـ lock.
     * لو الكورس مالوش سعر معلن → مفيش اشتراك مالي (كورس مجاني).
     */
    public function createForStudent(
        User $student,
        Course $course,
        int $actorId,
        ?string $startDate = null,
        ?float $monthlyFee = null,
        ?float $discount = null,
        ?string $discountReason = null
    ): ?CourseSubscription {
        return DB::transaction(function () use ($student, $course, $startDate, $monthlyFee, $discount, $discountReason) {
            // منع اشتراك active مكرر تحت قفل
            $existing = CourseSubscription::where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                return null;
            }

            // السعر الافتراضي من سعر الكورس الحالي المعنن
            $fee = $monthlyFee ?? (float) ($course->currentFee() ?? 0);

            if ($fee <= 0) {
                // كورس مجاني / بدون سعر معلن → مفيش التزام مالي
                return null;
            }

            $discount ??= 0.0;

            if ($discount > $fee) {
                throw ValidationException::withMessages([
                    'monthly_discount' => 'The discount cannot exceed the monthly fee.',
                ]);
            }

            return CourseSubscription::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'start_date' => $startDate ?? today()->toDateString(),
                'monthly_fee' => number_format($fee, 2, '.', ''),
                'monthly_discount' => number_format($discount, 2, '.', ''),
                'discount_reason' => $discount > 0 ? ($discountReason ?? 'Discount') : null,
                'status' => 'active',
            ]);
        });
    }

    /**
     * إغلاق الاشتراكات المالية النشطة (لما الطالب يسيب الكورس أو يتشال بالقوة).
     * التاريخ المالي القديم بيفضل موجود.
     */
    public function cancelActiveFor(int $studentId, int $courseId): void
    {
        // تحديث لكل model على حدة — عشان الـ Observer يسجل subscription_cancelled
        // في financial_audit_logs (الـ bulk update بيتخطى الـ model events)
        CourseSubscription::where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->get()
            ->each(function (CourseSubscription $subscription) {
                $subscription->update([
                    'status' => 'cancelled',
                    'end_date' => today()->toDateString(),
                ]);
            });
    }
}
