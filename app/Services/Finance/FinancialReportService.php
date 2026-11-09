<?php

namespace App\Services\Finance;

use App\Models\Course;
use App\Models\MonthlyCharge;
use App\Models\User;
use Illuminate\Support\Collection;

class FinancialReportService
{
    /**
     * ملخص مالي لشهر معين — من فواتير الشهر (ولّد الفواتير الأول عشان الأرقام تتكمل).
     */
    public function dashboard(int $year, int $month, ?int $courseId = null): array
    {
        $charges = MonthlyCharge::query()
            ->forPeriod($year, $month)
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->get();

        $expected = (float) $charges->sum('amount');
        $collected = (float) $charges->sum('paid_amount');

        $byStudent = $charges->groupBy('student_id')->map(function (Collection $rows) {
            $amount = (float) $rows->sum('amount');
            $paid = (float) $rows->sum('paid_amount');

            return [
                'amount' => $amount,
                'paid' => $paid,
                'status' => $paid >= $amount && $amount > 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
            ];
        });

        return [
            'period' => ['year' => $year, 'month' => $month],
            'expected' => number_format($expected, 2, '.', ''),
            'collected' => number_format($collected, 2, '.', ''),
            'remaining' => number_format(max(0, $expected - $collected), 2, '.', ''),
            'collection_rate' => $expected > 0
                ? round(($collected / $expected) * 100, 2)
                : 0,
            'charges_count' => $charges->count(),
            'students_count' => $byStudent->count(),
            'paid_students' => $byStudent->where('status', 'paid')->count(),
            'partially_paid_students' => $byStudent->where('status', 'partially_paid')->count(),
            'unpaid_students' => $byStudent->where('status', 'unpaid')->count(),
        ];
    }

    /**
     * إحصائيات مالية لكورس — لشهر معين أو إجمالي من أول نشاط.
     */
    public function courseStatistics(Course $course, ?int $year = null, ?int $month = null): array
    {
        $chargesQuery = MonthlyCharge::where('course_id', $course->id);

        if ($year && $month) {
            $chargesQuery->forPeriod($year, $month);
        }

        $charges = $chargesQuery->get();

        $expected = (float) $charges->sum('amount');
        $collected = (float) $charges->sum('paid_amount');

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            'period' => $year && $month ? ['year' => $year, 'month' => $month] : 'all_time',
            'students_count' => $course->students()->count(),
            'active_subscriptions' => $course->subscriptions()->active()->count(),
            'current_monthly_fee' => $course->currentFee(),
            'expected' => number_format($expected, 2, '.', ''),
            'collected' => number_format($collected, 2, '.', ''),
            'remaining' => number_format(max(0, $expected - $collected), 2, '.', ''),
            'collection_rate' => $expected > 0
                ? round(($collected / $expected) * 100, 2)
                : 0,
            'paid_charges' => $charges->where('status', 'paid')->count(),
            'partially_paid_charges' => $charges->where('status', 'partially_paid')->count(),
            'unpaid_charges' => $charges->where('status', 'unpaid')->count(),
        ];
    }

    /**
     * الملف المالي الكامل لطالب واحد.
     */
    public function studentFinancialProfile(User $student, ?array $restrictToCourseIds = null): array
    {
        $subscriptionsQuery = $student->subscriptions()->with('course');
        if ($restrictToCourseIds !== null) {
            $subscriptionsQuery->whereIn('course_id', $restrictToCourseIds);
        }
        $subscriptions = $subscriptionsQuery->get();

        $courses = $subscriptions->map(function ($subscription) {
            $charges = $subscription->charges()->get();
            $due = (float) $charges->sum('amount');
            $paid = (float) $charges->sum('paid_amount');

            $currentCharge = $subscription->charges()
                ->forPeriod(now()->year, now()->month)
                ->first();

            return [
                'course' => [
                    'id' => $subscription->course->id,
                    'title' => $subscription->course->title,
                ],
                'monthly_fee' => $subscription->monthly_fee,
                'monthly_discount' => $subscription->monthly_discount,
                'final_monthly_amount' => $subscription->final_monthly_amount,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'current_month' => $currentCharge ? [
                    'period' => $currentCharge->period_year.'-'.str_pad((string) $currentCharge->period_month, 2, '0', STR_PAD_LEFT),
                    'amount' => $currentCharge->amount,
                    'paid' => $currentCharge->paid_amount,
                    'status' => $currentCharge->status,
                ] : null,
                'total_due' => number_format($due, 2, '.', ''),
                'total_paid' => number_format($paid, 2, '.', ''),
                'remaining' => number_format(max(0, $due - $paid), 2, '.', ''),
                'charges' => $charges->map(fn ($charge) => [
                    'period' => $charge->period_year.'-'.str_pad((string) $charge->period_month, 2, '0', STR_PAD_LEFT),
                    'amount' => $charge->amount,
                    'paid' => $charge->paid_amount,
                    'status' => $charge->status,
                ]),
            ];
        });

        $totalDue = $courses->sum(fn ($c) => (float) $c['total_due']);
        $totalPaid = $courses->sum(fn ($c) => (float) $c['total_paid']);
        $credit = (float) $student->credits()->sum('remaining_amount');

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
            ],
            'totals' => [
                'total_due' => number_format($totalDue, 2, '.', ''),
                'total_paid' => number_format($totalPaid, 2, '.', ''),
                'total_remaining' => number_format(max(0, $totalDue - $totalPaid), 2, '.', ''),
                'current_credit' => number_format($credit, 2, '.', ''),
            ],
            'courses' => $courses,
            'payment_history' => $student->payments()
                ->with(['course', 'creator'])
                ->when($restrictToCourseIds !== null, fn ($q) => $q->whereIn('course_id', $restrictToCourseIds))
                ->latest('paid_at')
                ->get()
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'date' => $payment->paid_at,
                    'course' => $payment->course?->title,
                    'amount' => $payment->amount,
                    'method' => $payment->method,
                    'status' => $payment->status,
                    'recorded_by' => $payment->creator?->name,
                    'note' => $payment->note,
                    'cancellation_reason' => $payment->cancellation_reason,
                ]),
        ];
    }

    /**
     * المتأخرات — كل فاتورة غير مدفوعة/ناقصة، مع فلاتر.
     */
    public function overdue(array $filters = []): mixed
    {
        $query = MonthlyCharge::query()
            ->outstanding()
            ->with(['student', 'course']);

        $query->when($filters['course_id'] ?? null, fn ($q, $v) => $q->where('course_id', $v));
        $query->when($filters['student_id'] ?? null, fn ($q, $v) => $q->where('student_id', $v));
        $query->when($filters['year'] ?? null, fn ($q, $v) => $q->where('period_year', $v));
        $query->when($filters['month'] ?? null, fn ($q, $v) => $q->where('period_month', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        $query->when($filters['min_amount'] ?? null, function ($q, $v) {
            $q->whereRaw('(amount - paid_amount) >= ?', [(float) $v]);
        });

        return $query->orderBy('period_year')->orderBy('period_month');
    }

    /**
     * تقرير الإيراد الشهري لسنة كاملة.
     */
    public function monthlyRevenueReport(int $year, ?int $courseId = null): array
    {
        $charges = MonthlyCharge::query()
            ->where('period_year', $year)
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->get();

        $months = collect(range(1, 12))->map(function ($month) use ($charges) {
            $rows = $charges->where('period_month', $month);
            $expected = (float) $rows->sum('amount');
            $collected = (float) $rows->sum('paid_amount');

            return [
                'month' => $month,
                'expected' => number_format($expected, 2, '.', ''),
                'collected' => number_format($collected, 2, '.', ''),
                'remaining' => number_format(max(0, $expected - $collected), 2, '.', ''),
                'collection_rate' => $expected > 0 ? round(($collected / $expected) * 100, 2) : 0,
            ];
        });

        return [
            'year' => $year,
            'months' => $months,
            'total_expected' => number_format((float) $charges->sum('amount'), 2, '.', ''),
            'total_collected' => number_format((float) $charges->sum('paid_amount'), 2, '.', ''),
        ];
    }
}
