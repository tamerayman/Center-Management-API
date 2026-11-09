<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Lesson extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Lesson $lesson) {
            $lesson->materials()->each(function ($material) {
                if ($material->path) {
                    Storage::disk('public')->delete($material->path);
                }
            });
        });
    }

    protected $fillable = [
        'title',
        'description',
        'lesson_date',
        'start_time',
        'end_time',
        'location',
        'delivery_type',
        'course_id',
    ];

    protected function casts(): array
    {
        return [
            'lesson_date' => 'datetime',
            'start_time' => 'string:H:i',
            'end_time' => 'string:H:i',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function materials()
    {
        return $this->hasMany(LessonMaterial::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('lesson_date', '>=', now());
    }
}
