<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

protected $fillable = [
    'title',
    'description',
    'teacher_id'
];


    public function teacher()
{
    return $this->belongsTo(User::class, 'teacher_id');
}

public function lessons()
{
    return $this->hasMany(Lesson::class);
}
public function exams()
{
    return $this->hasMany(Exam::class);
}
public function students()
{
    return $this->belongsToMany(User::class,'course_student','course_id','student_id' )->withTimestamps();
}
}

