<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    protected $fillable = [
        'student_id',
        'exam_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * آخر لحظة مسموح للطالب يحل فيها:
     * وقت البدء + مدة الامتحان، بحد أقصى نهاية الامتحان للجميع.
     */
    public function deadline()
    {
        $deadline = $this->started_at->copy()->addMinutes($this->exam->duration);

        return $deadline->lt($this->exam->end_time)
            ? $deadline
            : $this->exam->end_time;
    }

    public function isExpired(): bool
    {
        return now()->gt($this->deadline());
    }
}
