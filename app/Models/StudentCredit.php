<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCredit extends Model
{
    protected $fillable = [
        'student_id',
        'source_payment_id',
        'amount',
        'remaining_amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_payment_id');
    }

    public function scopeWithBalance($query)
    {
        return $query->where('remaining_amount', '>', 0);
    }
}
