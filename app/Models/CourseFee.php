<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'monthly_fee',
        'effective_from',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * السعر الحالي للكورس (آخر سعر بدأ سريانه).
     */
    public function scopeCurrent($query, $courseId = null)
    {
        $query->where('effective_from', '<=', today());

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        return $query->orderByDesc('effective_from')->limit(1);
    }

    /**
     * السعر الحالي كقيمة — أو null لو الكورس مالوش سعر معلن.
     */
    public static function currentFeeValue(int $courseId): ?string
    {
        return static::query()
            ->where('course_id', $courseId)
            ->where('effective_from', '<=', today())
            ->orderByDesc('effective_from')
            ->value('monthly_fee');
    }
}
