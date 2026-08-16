<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;
use App\Models\Exam;
use Illuminate\Auth\Access\Response;

class QuestionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Question $question): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user ,   Exam $exam ): bool
    {
        //الكود اللي تحت ده غلط عشان السؤال لسه معتعملش اصلا فا لزما يبقي امتحان
        //return $user->role === 'teacher'&& $user->id === $question->exam->course->teacher_id;
        return $user->role === 'teacher'&& $exam->course->teacher_id === $user->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Question $question): bool
    {
        return $question->exam->course->teacher_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Question $question): bool
    {
        return $question->exam->course->teacher_id === $user->id;;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Question $question): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Question $question): bool
    {
        return false;
    }




    public function viewQuestions(User $user, Exam $exam): bool
{
    if ($user->role === 'teacher') {
        return $exam->course->teacher_id === $user->id;
    }

    if ($user->role === 'student') {
         return $exam->course->students()->where('student_id', $user->id)->exists();
   
    }

    return false;
}
}
