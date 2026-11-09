<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;

class LessonMaterialPolicy
{
    public function create(User $user, Lesson $lesson): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $lesson->course->teacher_id === $user->id);
    }

    public function update(User $user, LessonMaterial $material): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $material->lesson->course->teacher_id === $user->id);
    }

    public function delete(User $user, LessonMaterial $material): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $material->lesson->course->teacher_id === $user->id);
    }

    public function view(User $user, LessonMaterial $material): bool
    {
        if ($user->isAdmin() || $user->isTeacher()) {
            return $user->isAdmin()
                || $material->lesson->course->teacher_id === $user->id;
        }

        if ($user->isStudent()) {
            return $material->lesson->course
                ->students()
                ->where('student_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function viewAny(User $user, Lesson $lesson): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $lesson->course->teacher_id === $user->id;
        }

        if ($user->isStudent()) {
            return $lesson->course
                ->students()
                ->where('student_id', $user->id)
                ->exists();
        }

        return false;
    }
}
