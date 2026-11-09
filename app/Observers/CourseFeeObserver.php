<?php

namespace App\Observers;

use App\Models\CourseFee;
use App\Models\FinancialAuditLog;
use Illuminate\Support\Facades\Auth;

class CourseFeeObserver
{
    public function created(CourseFee $courseFee): void
    {
        FinancialAuditLog::create([
            'actor_id' => Auth::id(),
            'action' => 'fee_changed',
            'auditable_type' => $courseFee->getMorphClass(),
            'auditable_id' => $courseFee->id,
            'changes' => [
                'course_id' => $courseFee->course_id,
                'monthly_fee' => $courseFee->monthly_fee,
                'effective_from' => $courseFee->effective_from,
            ],
        ]);
    }
}
