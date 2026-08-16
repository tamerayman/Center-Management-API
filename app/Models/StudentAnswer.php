<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    protected $fillable = [
    'student_id',
    'question_id',
    'student_answer',
    'is_correct',
];
    public function student()
{
    return $this->belongsTo(User::class, 'student_id');
}
public function question()
{
    return $this->belongsTo(Question::class);
}
}
