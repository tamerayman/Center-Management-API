<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function create(User $user, Course $course): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $course->teacher_id === $user->id);
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->isAdmin() || $lesson->course->teacher_id === $user->id;
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->isAdmin() || $lesson->course->teacher_id === $user->id;
    }
}
