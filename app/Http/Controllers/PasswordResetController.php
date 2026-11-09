<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * إرسال لينك إعادة تعيين كلمة السر على الإيميل.
     * الرد دايماً 200 بنفس الرسالة — لا يُعرف منه إيه الإيميلات المسجلة.
     */
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => 'Reset password link sent to your email.',
        ]);
    }

    /**
     * تعيين كلمة سر جديدة باستخدام التوكن الواصل بالإيميل.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete(); // إلغاء كل التوكنات بعد تغيير كلمة السر

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Password has been reset successfully. You can now login.',
        ]);
    }
}
