<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;

/**
 * ولي الأمر — كيان مستقل تماماً عن جدول users.
 * Authentication عبر Sanctum (نفس جدول personal_access_tokens بـ morph).
 * Read-Only Portal: لا يملك أي صلاحية كتابة على أي بيانات.
 */
class Guardian extends Model implements AuthenticatableContract
{
    use Authenticatable, Authorizable, HasApiTokens;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student', 'guardian_id', 'student_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(GuardianNotification::class);
    }

    /**
     * هل ولي الأمر ده مربوط بالطالب ده؟ — أساس كل الـ authorization.
     */
    public function hasChild(User $student): bool
    {
        return $this->children()->where('student_id', $student->id)->exists();
    }
}
