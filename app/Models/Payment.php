<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'monthly_charge_id',
        'amount',
        'allocated_amount',
        'paid_at',
        'method',
        'status',
        'created_by',
        'note',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
            'paid_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(MonthlyCharge::class, 'monthly_charge_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
