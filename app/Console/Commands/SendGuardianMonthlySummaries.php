<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GuardianNotifier;
use App\Services\ProgressService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendGuardianMonthlySummaries extends Command
{
    protected $signature = 'guardian:send-monthly-summaries {--month=} {--year=}';

    protected $description = 'ملخص شهري لولي الأمر: حضور + آخر النتايج + الوضع المالي لكل طفل';

    public function handle(GuardianNotifier $notifier): int
    {
        $month = (int) ($this->option('month') ?? now()->subMonth()->month);
        $year = (int) ($this->option('year') ?? now()->subMonth()->year);

        $monthName = Carbon::create($year, $month, 1)->format('F');

        // الطلاب اللي عندهم أولياء أمور فقط
        $students = User::where('role', 'student')
            ->has('guardians')
            ->withCount('guardians')
            ->get();

        $sent = 0;
        $progressService = app(ProgressService::class);

        foreach ($students as $student) {
            // حضور الشهر — بنفس المعادلة المركزية
            $monthAttendanceQuery = $student->attendances()
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month);

            $attendanceTotal = (clone $monthAttendanceQuery)->count();
            $attendanceRate = $progressService->attendancePercentageFrom($monthAttendanceQuery) ?? 0;

            // نتايج الشهر
            $resultsCount = $student->results()
                ->whereYear('submitted_at', $year)
                ->whereMonth('submitted_at', $month)
                ->count();

            // الوضع المالي للشهر
            $charges = $student->charges()->forPeriod($year, $month)->get();
            $due = (float) $charges->sum('amount');
            $paid = (float) $charges->sum('paid_amount');

            $sent += $notifier->notifyGuardians(
                $student,
                'Monthly Report',
                sprintf(
                    'Monthly report for %s (%s %d): attendance rate %s%% (%d sessions), %d new result(s), finance: %.2f EGP due / %.2f EGP paid.',
                    $student->name,
                    $monthName,
                    $year,
                    $attendanceRate,
                    $attendanceTotal,
                    $resultsCount,
                    $due,
                    $paid,
                )
            );
        }

        $this->info("Monthly summaries sent: {$sent}");

        return self::SUCCESS;
    }
}
