<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Course $course) {
            $course->lessons()->each(function ($lesson) {
                $lesson->materials()->each(function ($material) {
                    if ($material->path) {
                        Storage::disk('public')->delete($material->path);
                    }
                });
            });
        });
    }

    protected $fillable = [
        'title',
        'description',
        'teacher_id',
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
        return $this->belongsToMany(User::class, 'course_student', 'course_id', 'student_id')->withTimestamps();
    }

    public function fees()
    {
        return $this->hasMany(CourseFee::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(CourseSubscription::class);
    }

    /**
     * السعر الشهري الحالي المعلن للكورس — أو null لو مفيش سعر معلن.
     */
    public function currentFee(): ?string
    {
        return CourseFee::currentFeeValue($this->id);
    }
}
