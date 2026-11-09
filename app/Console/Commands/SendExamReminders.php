<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Services\GuardianNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendExamReminders extends Command
{
    protected $signature = 'guardian:send-exam-reminders';

    protected $description = 'إشعار أولياء الأمور بامتحانات الأبناء غداً';

    public function handle(GuardianNotifier $notifier): int
    {
        $tomorrowStart = Carbon::tomorrow()->startOfDay();
        $tomorrowEnd = Carbon::tomorrow()->endOfDay();

        $exams = Exam::with(['course.students', 'course'])
            ->whereBetween('start_time', [$tomorrowStart, $tomorrowEnd])
            ->get();

        $sent = 0;

        foreach ($exams as $exam) {
            foreach ($exam->course->students as $student) {
                $sent += $notifier->notifyGuardians(
                    $student,
                    'Exam Reminder',
                    sprintf(
                        "%s has an exam tomorrow: '%s' (%s course) at %s.",
                        $student->name,
                        $exam->title,
                        $exam->course->title,
                        $exam->start_time->format('H:i'),
                    )
                );
            }
        }

        $this->info("Exam reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
