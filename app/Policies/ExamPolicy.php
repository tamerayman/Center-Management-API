<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function create(User $user, Course $course): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $course->teacher_id === $user->id);
    }

    public function update(User $user, Exam $exam): bool
    {
        return $user->isAdmin() || $exam->course->teacher_id === $user->id;
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $user->isAdmin() || $exam->course->teacher_id === $user->id;
    }

    public function viewResults(User $user, Exam $exam): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $exam->course->teacher_id === $user->id);
    }
}
