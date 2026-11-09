<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, Course $course): bool
    {
        return $user->isAdmin() || $user->id === $course->teacher_id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->isAdmin() || $user->id === $course->teacher_id;
    }
}
