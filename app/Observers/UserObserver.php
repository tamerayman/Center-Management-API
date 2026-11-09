<?php

namespace App\Observers;

use App\Models\Profile;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        Profile::create([
            'user_id' => $user->id,
        ]);

        // كل طالب جديد بياخد كود ربط ولي أمر فريد (يظهر في البروفايل بتاعه فقط)
        if ($user->role === 'student') {
            $user->forceFill(['guardian_code' => User::generateGuardianCode()])->save();
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
