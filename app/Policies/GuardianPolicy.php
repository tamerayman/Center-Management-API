<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;

/**
 * أساس الـ authorization كله: ولي الأمر يقرأ فقط بيانات أطفاله المرتبطين به.
 * أي student مش في guardian_student بتاعه → 403 حتى لو عرف الـ ID.
 */
class GuardianPolicy
{
    public function viewChild(Guardian $guardian, User $student): bool
    {
        return $guardian->hasChild($student);
    }

    /**
     * ربط طفل جديد — الكود لازم يكون لطالب مش مربوط بيه بالفعل
     * (الـ 409 للحالة المكررة بيتعالج في الـ controller).
     */
    public function linkChild(Guardian $guardian, User $student): bool
    {
        return ! $guardian->hasChild($student);
    }
}
