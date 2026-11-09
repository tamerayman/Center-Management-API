<?php

namespace App\Services\Finance;

use App\Events\PaymentCancelled;
use App\Events\PaymentRecorded;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\FinancialAuditLog;
use App\Models\MonthlyCharge;
use App\Models\Payment;
use App\Models\StudentCredit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private ChargeService $charges) {}

    /**
     * تسجيل دفعة — يدعم partial payments والـ overpayment (credit) واستخدام الـ credit.
     *
     * الـ payment بيسجل المبلغ الكامل الذي دفعه الطالب فعلياً.
     * لو المبلغ أكبر من المتبقي على الفاتورة → الفائض بيتحول student credit.
     * لو use_credit=true → الرصيد المتاح بيتطبق على الفاتورة الأول.
     */
    public function recordPayment(User $actor, User $student, Course $course, array $data): Payment
    {
        return DB::transaction(function () use ($actor, $student, $course, $data) {
            // الاشتراك المالي النشط شرط لوجود استحقاق
            $subscription = CourseSubscription::where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->whereIn('status', ['active', 'paused', 'completed'])
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                throw ValidationException::withMessages([
                    'student_id' => 'This student has no financial subscription for this course.',
                ]);
            }

            // لو مفيش فاتورة للشهر → تتولد تلقائياً بسعر الاشتراك ثم يتم الدفع
            $charge = $this->charges->findOrCreate($subscription, (int) $data['period_year'], (int) $data['period_month']);
            // إعادة قراءة الفاتورة مع قفل — يجب أن يكون داخل transaction
            $charge = MonthlyCharge::where('id', $charge->id)->lockForUpdate()->firstOrFail();

            $useCredit = (bool) ($data['use_credit'] ?? false);

            // 1) استخدام الرصيد المتاح (FIFO) قبل النقد
            if ($useCredit) {
                $this->applyCredits($student, $charge);
                $charge->refresh();
            }

            // 2) تسجيل الدفعة الفعلية كاملة
            $cashAmount = (float) $data['amount'];

            // لو كل التغطية من الـ credit ومفيش كاش — نسمح فقط لو use_credit مفعّل
            if ($cashAmount == 0 && ! $useCredit) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment amount must be greater than zero.',
                ]);
            }

            $remainingOnCharge = max(0, (float) $charge->amount - (float) $charge->paid_amount);

            $allocated = min($cashAmount, $remainingOnCharge);
            $excess = $cashAmount - $allocated;

            $payment = Payment::create([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'monthly_charge_id' => $charge->id,
                'amount' => number_format($cashAmount, 2, '.', ''),
                'allocated_amount' => number_format($allocated, 2, '.', ''),
                'paid_at' => $data['paid_at'] ?? today()->toDateString(),
                'method' => $data['method'],
                'status' => 'active',
                'created_by' => $actor->id,
                'note' => $data['note'] ?? null,
            ]);

            // 3) الزيادة عن المتبقي → student credit (مفيش قرش بيضيع)
            if ($excess > 0) {
                StudentCredit::create([
                    'student_id' => $student->id,
                    'source_payment_id' => $payment->id,
                    'amount' => number_format($excess, 2, '.', ''),
                    'remaining_amount' => number_format($excess, 2, '.', ''),
                    'note' => 'Advance from '.$course->title.' payment ('.$data['period_year'].'-'.str_pad((string) $data['period_month'], 2, '0', STR_PAD_LEFT).')',
                ]);
            }

            $charge->recalculate();

            event(new PaymentRecorded($payment));

            return $payment->fresh();
        });
    }

    /**
     * تطبيق أرصدة الطالب المتاحة على الفاتورة (FIFO) — نسجله في الـ audit.
     */
    private function applyCredits(User $student, $charge): float
    {
        $remaining = max(0, (float) $charge->amount - (float) $charge->paid_amount);

        if ($remaining <= 0) {
            return 0.0;
        }

        $credits = StudentCredit::where('student_id', $student->id)
            ->withBalance()
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        $applied = 0.0;

        foreach ($credits as $credit) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((float) $credit->remaining_amount, $remaining);

            $credit->update([
                'remaining_amount' => number_format((float) $credit->remaining_amount - $take, 2, '.', ''),
            ]);

            $remaining -= $take;
            $applied += $take;
        }

        if ($applied > 0) {
            $charge->credit_applied = number_format((float) $charge->credit_applied + $applied, 2, '.', '');
            $charge->save();
            $charge->recalculate();

            FinancialAuditLog::create([
                'actor_id' => auth()->id(),
                'action' => 'credit_used',
                'auditable_type' => $charge->getMorphClass(),
                'auditable_id' => $charge->id,
                'changes' => ['amount_applied' => number_format($applied, 2, '.', '')],
            ]);
        }

        return $applied;
    }

    /**
     * تعديل دفعة — يعيد توزيع الـ allocation ويعيد حساب الفاتورة داخل transaction.
     */
    public function updatePayment(User $actor, Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            $charge = $payment->charge()->lockForUpdate()->first();

            $oldAmount = (float) $payment->amount;
            $newAmount = (float) ($data['amount'] ?? $oldAmount);

            if ($charge) {
                // نفضي allocation الدفعة القديم مؤقتاً عشان نحسب المتبقي بنظافة
                $othersPaid = (float) $charge->payments()->where('status', 'active')
                    ->whereKeyNot($payment->id)
                    ->sum('allocated_amount');

                $freeSpace = max(0, (float) $charge->amount - $othersPaid - (float) $charge->credit_applied);

                // لو الدفعة القديمة كانت أنشأت credit واتستهلك جزء منه → نمنع التعديل
                $oldExcess = $oldAmount - (float) $payment->allocated_amount;

                if ($oldExcess > 0) {
                    $credit = StudentCredit::where('source_payment_id', $payment->id)->lockForUpdate()->first();

                    if ($credit && (float) $credit->remaining_amount < (float) $credit->amount) {
                        throw ValidationException::withMessages([
                            'amount' => 'This payment produced a credit that is already partially used — it cannot be modified.',
                        ]);
                    }
                }

                $newAllocated = min($newAmount, $freeSpace);
                $newExcess = $newAmount - $newAllocated;

                $payment->allocated_amount = number_format($newAllocated, 2, '.', '');

                // تحديث الـ credit المرتبط بالدفعة
                $credit = StudentCredit::where('source_payment_id', $payment->id)->lockForUpdate()->first();

                if ($newExcess > 0) {
                    if ($credit) {
                        $credit->update([
                            'amount' => number_format($newExcess, 2, '.', ''),
                            'remaining_amount' => number_format($newExcess, 2, '.', ''),
                        ]);
                    } else {
                        StudentCredit::create([
                            'student_id' => $payment->student_id,
                            'source_payment_id' => $payment->id,
                            'amount' => number_format($newExcess, 2, '.', ''),
                            'remaining_amount' => number_format($newExcess, 2, '.', ''),
                            'note' => 'Advance (payment updated)',
                        ]);
                    }
                } elseif ($credit) {
                    $credit->update([
                        'amount' => '0.00',
                        'remaining_amount' => '0.00',
                    ]);
                }
            }

            $payment->amount = number_format($newAmount, 2, '.', '');
            $payment->paid_at = $data['paid_at'] ?? $payment->paid_at;
            $payment->method = $data['method'] ?? $payment->method;
            $payment->note = array_key_exists('note', $data) ? $data['note'] : $payment->note;
            $payment->save();

            $charge?->recalculate();

            return $payment->fresh();
        });
    }

    /**
     * إلغاء دفعة — بلا حذف فيزيائي، ويعيد حساب الفاتورة والرصيد المرتبط.
     */
    public function cancelPayment(User $actor, Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($actor, $payment, $reason) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'payment' => 'This payment is already cancelled.',
                ]);
            }

            $charge = $payment->charge()->lockForUpdate()->first();

            // الرصيد الذي نشأ من هذه الدفعة يلغى معها — لو اتعمل منه استخدام جزئي نمنع
            $credit = StudentCredit::where('source_payment_id', $payment->id)->lockForUpdate()->first();

            if ($credit && (float) $credit->remaining_amount < (float) $credit->amount) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment produced a credit that is already partially used — cancellation requires handling the credit first.',
                ]);
            }

            $credit?->update(['remaining_amount' => '0.00']);

            $payment->update([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $charge?->recalculate();

            event(new PaymentCancelled($payment->fresh()));

            return $payment->fresh();
        });
    }
}
