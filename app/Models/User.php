<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use  HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
  
    public function profile()
{
    return $this->hasOne(Profile::class);
}
    public function courses(){

    return $this->hasMany(Course::class, 'teacher_id');
    }


    public function enrolledCourses()
{
    return $this->belongsToMany(Course::class,'course_student','student_id','course_id')->withTimestamps();
}

public function notifications()
{
    return $this->hasMany(Notification::class);
}
public function attendances()
{
    return $this->hasMany(Attendance::class, 'student_id');
}

public function results()
{
    return $this->hasMany(StudentResult::class, 'student_id');
}

public function studentAnswers()
{
    return $this->hasMany(StudentAnswer::class, 'student_id');
}

}
