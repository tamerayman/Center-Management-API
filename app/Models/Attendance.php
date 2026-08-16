<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
     protected $fillable = [
        'student_id',
        'lesson_id',
        'status',
    ];
    public function student()
{
    return $this->belongsTo(User::class, 'student_id');
}
public function lesson()
{
    return $this->belongsTo(Lesson::class);
}
}
