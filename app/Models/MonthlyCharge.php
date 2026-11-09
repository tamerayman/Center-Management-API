<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyCharge extends Model
{
    protected $fillable = [
        'subscription_id',
        'student_id',
        'course_id',
        'period_year',
        'period_month',
        'amount',
        'paid_amount',
        'credit_applied',
        'status',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'credit_applied' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    /**
     * المتبقي على هذه الفاتورة.
     */
    public function getRemainingAmountAttribute(): string
    {
        return number_format(
            max(0, (float) $this->amount - (float) $this->paid_amount),
            2,
            '.',
            ''
        );
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CourseSubscription::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * إعادة حساب paid_amount من مصدر الحقيقة (المدفوعات النشطة + الـ credit المطبق)
     * وتحديث الحالة — بتتنادى جوه transaction بعد أي عملية مالية.
     */
    public function recalculate(): void
    {
        $paid = (float) $this->payments()->where('status', 'active')->sum('allocated_amount')
            + (float) $this->credit_applied;

        $this->paid_amount = number_format($paid, 2, '.', '');
        $this->status = match (true) {
            $paid >= (float) $this->amount => 'paid',
            $paid > 0 => 'partially_paid',
            default => 'unpaid',
        };

        $this->save();
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['unpaid', 'partially_paid']);
    }
}
