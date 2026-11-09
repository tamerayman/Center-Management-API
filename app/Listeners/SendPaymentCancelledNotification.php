<?php

namespace App\Listeners;

use App\Events\PaymentCancelled;
use App\Models\Notification;
use App\Services\GuardianNotifier;
use Carbon\Carbon;

class SendPaymentCancelledNotification
{
    public function handle(PaymentCancelled $event): void
    {
        $payment = $event->payment->loadMissing(['course', 'charge', 'student']);

        Notification::create([
            'user_id' => $payment->student_id,
            'title' => 'Payment Cancelled',
            'message' => sprintf(
                'A payment of %s EGP for your %s course (%s %d) was cancelled.',
                number_format((float) $payment->amount, 2),
                $payment->course?->title ?? 'course',
                Carbon::create($payment->charge?->period_year ?? now()->year, $payment->charge?->period_month ?? now()->month, 1)->format('F'),
                $payment->charge?->period_year ?? now()->year,
            ),
        ]);

        app(GuardianNotifier::class)->notifyGuardians(
            $payment->student,
            'Payment Cancelled',
            sprintf(
                "%s's payment of %s EGP for %s course (%s %d) was cancelled.",
                $payment->student->name,
                number_format((float) $payment->amount, 2),
                $payment->course?->title ?? 'the',
                Carbon::create($payment->charge?->period_year ?? now()->year, $payment->charge?->period_month ?? now()->month, 1)->format('F'),
                $payment->charge?->period_year ?? now()->year,
            )
        );
    }
}
