<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;

class LessonMaterialPolicy
{
    public function create(User $user, Lesson $lesson): bool
    {
        return $user->role === 'teacher'
            && $lesson->course->teacher_id === $user->id;
    }

    public function update(User $user, LessonMaterial $material): bool
    {
        return $user->role === 'teacher'
            && $material->lesson->course->teacher_id === $user->id;
    }

    public function delete(User $user, LessonMaterial $material): bool
    {
        return $user->role === 'teacher'
            && $material->lesson->course->teacher_id === $user->id;
    }

    public function view(User $user, LessonMaterial $material)
{
    if ($user->role === 'teacher') {
        return $material->lesson->course->teacher_id === $user->id;
    }

    if ($user->role === 'student') {
        return $material->lesson->course
            ->students()
            ->where('student_id', $user->id)
            ->exists();
    }

    return false;
}

public function viewAny(User $user, Lesson $lesson)
{
    if ($user->role === 'teacher') {
        return $lesson->course->teacher_id === $user->id;
    }

    if ($user->role === 'student') {
        return $lesson->course
            ->students()
            ->where('student_id', $user->id)
            ->exists();
    }

    return false;
}
}