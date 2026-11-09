<?php

namespace App\Observers;

use App\Models\CourseSubscription;
use App\Models\FinancialAuditLog;
use Illuminate\Support\Facades\Auth;

class CourseSubscriptionObserver
{
    public function created(CourseSubscription $subscription): void
    {
        FinancialAuditLog::create([
            'actor_id' => Auth::id(),
            'action' => 'subscription_created',
            'auditable_type' => $subscription->getMorphClass(),
            'auditable_id' => $subscription->id,
            'changes' => [
                'student_id' => $subscription->student_id,
                'course_id' => $subscription->course_id,
                'monthly_fee' => $subscription->monthly_fee,
                'monthly_discount' => $subscription->monthly_discount,
                'start_date' => $subscription->start_date,
            ],
        ]);
    }

    public function updated(CourseSubscription $subscription): void
    {
        // إلغاء الاشتراك
        if ($subscription->wasChanged('status') && $subscription->status === 'cancelled') {
            $after = ['status' => 'cancelled', 'end_date' => $subscription->end_date];

            if (request()?->filled('cancellation_reason')) {
                $after['cancellation_reason'] = request('cancellation_reason');
            }

            FinancialAuditLog::create([
                'actor_id' => Auth::id(),
                'action' => 'subscription_cancelled',
                'auditable_type' => $subscription->getMorphClass(),
                'auditable_id' => $subscription->id,
                'changes' => [
                    'before' => ['status' => $subscription->getOriginal('status')],
                    'after' => $after,
                ],
            ]);

            return;
        }

        // تعديل الخصم
        if ($subscription->wasChanged('monthly_discount')) {
            FinancialAuditLog::create([
                'actor_id' => Auth::id(),
                'action' => 'discount_updated',
                'auditable_type' => $subscription->getMorphClass(),
                'auditable_id' => $subscription->id,
                'changes' => [
                    'monthly_discount' => [
                        'before' => $subscription->getOriginal('monthly_discount'),
                        'after' => $subscription->monthly_discount,
                    ],
                    'discount_reason' => $subscription->discount_reason,
                ],
            ]);
        }
    }
}
