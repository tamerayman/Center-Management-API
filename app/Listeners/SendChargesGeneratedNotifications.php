<?php

namespace App\Listeners;

use App\Events\ChargesGenerated;
use App\Models\Notification;
use App\Services\GuardianNotifier;
use Carbon\Carbon;

class SendChargesGeneratedNotifications
{
    public function handle(ChargesGenerated $event): void
    {
        $monthName = Carbon::create($event->periodYear, $event->periodMonth, 1)->format('F');

        $now = now();
        $notifier = app(GuardianNotifier::class);

        foreach ($event->charges as $charge) {
            $student = $charge->student;
            $courseTitle = $charge->course?->title ?? 'course';
            $amount = number_format((float) $charge->amount, 2);

            Notification::create([
                'user_id' => $charge->student_id,
                'title' => 'Payment Due',
                'message' => sprintf(
                    'Your %s course subscription for %s %d is due for payment (%s EGP).',
                    $courseTitle,
                    $monthName,
                    $event->periodYear,
                    $amount,
                ),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $notifier->notifyGuardians(
                $student,
                'Payment Due',
                sprintf(
                    "%s's monthly fee for %s course (%s %d) is due for payment (%s EGP).",
                    $student->name,
                    $courseTitle,
                    $monthName,
                    $event->periodYear,
                    $amount,
                )
            );
        }
    }
}
