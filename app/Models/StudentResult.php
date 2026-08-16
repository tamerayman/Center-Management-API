<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentResult extends Model
{
    protected $fillable = [
    'student_id',
    'exam_id',
    'score',
    'total_marks',
    'status',
    'submitted_at',
];
    public function student()
{
    return $this->belongsTo(User::class, 'student_id');
}
public function exam()
{
    return $this->belongsTo(Exam::class);
}
}
