<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class StudentResultPolicy
{
    public function viewResults(User $user, Exam $exam): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $exam->course->teacher_id === $user->id);
    }
}
