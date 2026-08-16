<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LessonMaterial extends Model
{
    use HasFactory;

       protected $fillable = [
        'lesson_id',
        'title',
        'type',
        'path',
        'external_url',
        'mime_type',
        'file_size',
    ];
   public function lesson()
{
    return $this->belongsTo(Lesson::class);
} 
}
