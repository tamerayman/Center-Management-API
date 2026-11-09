<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isSecretary(): bool
    {
        return $this->role === 'secretary';
    }

    /**
     * موظف مالي — admin أو secretary.
     */
    public function isFinancialStaff(): bool
    {
        return $this->isAdmin() || $this->isSecretary();
    }

    /**
     * كود ربط ولي الأمر — random cryptographic، بدون أي علاقة بالـ student_id.
     * public لتسهيل regeneration مستقبلاً من غير تغيير معماري.
     */
    public static function generateGuardianCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // بدون 0/O/1/I عشان ما تتلخبطش

        do {
            $code = 'GUARD-';
            for ($i = 0; $i < 4; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code .= '-';
            for ($i = 0; $i < 4; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('guardian_code', $code)->exists());

        return $code;
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'course_student', 'student_id', 'course_id')->withTimestamps();
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

    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class, 'student_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(CourseSubscription::class, 'student_id');
    }

    public function credits()
    {
        return $this->hasMany(StudentCredit::class, 'student_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'student_id');
    }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student', 'student_id', 'guardian_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function charges()
    {
        return $this->hasMany(MonthlyCharge::class, 'student_id');
    }
}
