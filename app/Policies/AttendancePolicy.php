<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class AttendancePolicy
{
    public function create(User $user, Lesson $lesson): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $lesson->course->teacher_id === $user->id);
    }

    public function viewAttendance(User $user, Lesson $lesson): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $lesson->course->teacher_id === $user->id);
    }
}
