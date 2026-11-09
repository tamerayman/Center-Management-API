<?php

namespace App\Observers;

use App\Models\FinancialAuditLog;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

class PaymentObserver
{
    /**
     * الـ actor اللي مسجل في النظام وقت العملية — أو null لو سياق تقني.
     */
    private function actorId(): ?int
    {
        return Auth::id();
    }

    public function created(Payment $payment): void
    {
        FinancialAuditLog::create([
            'actor_id' => $this->actorId(),
            'action' => 'payment_created',
            'auditable_type' => $payment->getMorphClass(),
            'auditable_id' => $payment->id,
            'changes' => [
                'amount' => $payment->amount,
                'allocated_amount' => $payment->allocated_amount,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at,
                'monthly_charge_id' => $payment->monthly_charge_id,
                'student_id' => $payment->student_id,
                'course_id' => $payment->course_id,
            ],
        ]);
    }

    public function updated(Payment $payment): void
    {
        // إلغاء الدفعة عملية مميزة في الـ audit
        if ($payment->wasChanged('status') && $payment->status === 'cancelled') {
            FinancialAuditLog::create([
                'actor_id' => $payment->cancelled_by ?? $this->actorId(),
                'action' => 'payment_cancelled',
                'auditable_type' => $payment->getMorphClass(),
                'auditable_id' => $payment->id,
                'changes' => [
                    'before' => ['status' => 'active', 'amount' => $payment->getOriginal('amount')],
                    'after' => [
                        'status' => 'cancelled',
                        'amount' => $payment->amount,
                        'cancellation_reason' => $payment->cancellation_reason,
                    ],
                ],
            ]);

            return;
        }

        // أي تعديل آخر على دفعة نشطة (مبلغ/تاريخ/طريقة/ملاحظة)
        $auditableFields = ['amount', 'allocated_amount', 'paid_at', 'method', 'note', 'monthly_charge_id'];

        $changes = collect($auditableFields)
            ->mapWithKeys(fn ($field) => [
                $field => [
                    'before' => $payment->getOriginal($field),
                    'after' => $payment->getAttribute($field),
                ],
            ])
            ->filter(fn ($change) => $change['before'] != $change['after']);

        if ($changes->isEmpty()) {
            return;
        }

        FinancialAuditLog::create([
            'actor_id' => $this->actorId(),
            'action' => 'payment_updated',
            'auditable_type' => $payment->getMorphClass(),
            'auditable_id' => $payment->id,
            'changes' => $changes->all(),
        ]);
    }
}
