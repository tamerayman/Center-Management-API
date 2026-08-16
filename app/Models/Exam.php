<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $fillable=[
        'course_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'duration',
        'total_marks'
    ];

    public function course()
{
    return $this->belongsTo(Course::class);
}
public function questions()
{
    return $this->hasMany(Question::class);
}
public function results()
{
    return $this->hasMany(StudentResult::class);
}
}
