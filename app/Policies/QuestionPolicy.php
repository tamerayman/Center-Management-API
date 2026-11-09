<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function create(User $user, Exam $exam): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $exam->course->teacher_id === $user->id);
    }

    public function update(User $user, Question $question): bool
    {
        return $user->isAdmin() || $question->exam->course->teacher_id === $user->id;
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->isAdmin() || $question->exam->course->teacher_id === $user->id;
    }

    public function viewQuestions(User $user, Exam $exam): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $exam->course->teacher_id === $user->id;
        }

        if ($user->isStudent()) {
            return $exam->course
                ->students()
                ->where('student_id', $user->id)
                ->exists();
        }

        return false;
    }
}
