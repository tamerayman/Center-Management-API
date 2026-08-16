<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;
use App\Models\Course;
use Illuminate\Auth\Access\Response;

class ExamPolicy
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
    public function view(User $user, Exam $exam): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
   public function create(User $user, Course $course)
{
    return  $user->role === 'teacher' && $course->teacher_id === $user->id;
    //or
    //الكود اللي تحت ده غلط عشان المتحان لسه معتعملش اصلا فا لزما يبقي كورس
    // return $user->role === 'teacher' && $user->id===$exam->course->teacher_id;
    
}

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Exam $exam): bool
    {
       return $exam->course->teacher_id===$user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Exam $exam): bool
    {
        return $exam->course->teacher_id===$user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Exam $exam): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Exam $exam): bool
    {
        return false;
    }

    public function viewResults(User $user, Exam $exam): bool
    {
        return $user->role === 'teacher' && $exam->course->teacher_id === $user->id;
    }
}
