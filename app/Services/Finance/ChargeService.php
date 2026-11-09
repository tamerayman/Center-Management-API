<?php

namespace App\Services\Finance;

use App\Events\ChargesGenerated;
use App\Models\CourseSubscription;
use App\Models\MonthlyCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ChargeService
{
    public function __construct() {}

    /**
     * توليد فواتير شهر كامل — لكل الاشتراكات النشطة أو لكورس محدد.
     * الـ unique constraint بيمنع الازدواج حتى لو الاتصال تم مرتين.
     */
    public function generateForPeriod(int $year, int $month, ?int $courseId = null): array
    {
        return DB::transaction(function () use ($year, $month, $courseId) {
            $subscriptions = CourseSubscription::query()
                ->where('status', 'active')
                // اشتراك بدأ بعد الشهر المطلوب مالوش استحقاق فيه
                ->where(function ($q) use ($year, $month) {
                    $q->where('start_date', '<=', Carbon::create($year, $month, 1)->endOfMonth())
                        ->orWhereNull('start_date');
                })
                ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
                ->get();

            $charges = collect();

            foreach ($subscriptions as $subscription) {
                // اللي عنده فاتورة بالفعل مش بيتعد — الـ unique constraint حارس أخير
                $exists = MonthlyCharge::where('subscription_id', $subscription->id)
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->exists();

                if (! $exists) {
                    $charges->push($this->findOrCreate($subscription, $year, $month, false));
                }
            }

            $charges = $charges->filter()->values();

            if ($charges->isNotEmpty()) {
                event(new ChargesGenerated($charges, $year, $month));
            }

            return [
                'created' => $charges->count(),
                'charges' => $charges,
            ];
        });
    }

    /**
     * إيجاد فاتورة الشهر لاشتراك ما أو إنشاؤها لو مش موجودة.
     * لا يسمح بفواتير مكررة (unique على subscription+year+month).
     */
    public function findOrCreate(CourseSubscription $subscription, int $year, int $month, bool $lock = true): MonthlyCharge
    {
        $query = MonthlyCharge::where('subscription_id', $subscription->id)
            ->where('period_year', $year)
            ->where('period_month', $month);

        $charge = $lock ? $query->lockForUpdate()->first() : $query->first();

        if ($charge) {
            return $charge;
        }

        try {
            // snapshot من السعر النهائي للاشتراك — التاريخ القديم لا يتأثر لاحقاً
            return MonthlyCharge::create([
                'subscription_id' => $subscription->id,
                'student_id'      => $subscription->student_id,
                'course_id'       => $subscription->course_id,
                'period_year'     => $year,
                'period_month'    => $month,
                'amount'          => $subscription->final_monthly_amount,
                'paid_amount'     => 0,
                'credit_applied'  => 0,
                'status'          => 'unpaid',
                'due_date'        => Carbon::create($year, $month, 1)->toDateString(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // سباق بين طلبين متزامنين — الـ unique constraint منع الازدواج، نجيب الموجود
            if ($e->errorInfo[1] === 1062) {
                return $query->firstOrFail();
            }
            throw $e;
        }
    }
}
