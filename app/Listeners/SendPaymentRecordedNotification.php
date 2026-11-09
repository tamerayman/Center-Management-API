<?php

namespace App\Listeners;

use App\Events\PaymentRecorded;
use App\Models\Notification;
use App\Services\GuardianNotifier;
use Carbon\Carbon;

class SendPaymentRecordedNotification
{
    public function handle(PaymentRecorded $event): void
    {
        $payment = $event->payment->loadMissing(['course', 'charge', 'student']);

        Notification::create([
            'user_id' => $payment->student_id,
            'title' => 'Payment Recorded',
            'message' => sprintf(
                'A payment of %s EGP was recorded for your %s course (%s %d).',
                number_format((float) $payment->amount, 2),
                $payment->course?->title ?? 'course',
                Carbon::create($payment->charge?->period_year ?? now()->year, $payment->charge?->period_month ?? now()->month, 1)->format('F'),
                $payment->charge?->period_year ?? now()->year,
            ),
        ]);

        app(GuardianNotifier::class)->notifyGuardians(
            $payment->student,
            'Payment Recorded',
            sprintf(
                "%s's payment of %s EGP for %s course (%s %d) was recorded.",
                $payment->student->name,
                number_format((float) $payment->amount, 2),
                $payment->course?->title ?? 'the',
                Carbon::create($payment->charge?->period_year ?? now()->year, $payment->charge?->period_month ?? now()->month, 1)->format('F'),
                $payment->charge?->period_year ?? now()->year,
            )
        );
    }
}
