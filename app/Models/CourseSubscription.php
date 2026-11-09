<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSubscription extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'start_date',
        'end_date',
        'monthly_fee',
        'monthly_discount',
        'discount_reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
            'monthly_discount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * المبلغ الشهري النهائي المتفق عليه مع الطالب.
     */
    public function getFinalMonthlyAmountAttribute(): string
    {
        return number_format((float) $this->monthly_fee - (float) $this->monthly_discount, 2, '.', '');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(MonthlyCharge::class, 'subscription_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
