<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * إعادة إرسال لينك تفعيل الإيميل للمستخدم المسجل دخوله.
     */
    public function send(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent.',
        ]);
    }

    /**
     * تفعيل الإيميل عبر اللينك الموقّع (signed URL).
     */
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'message' => 'Invalid verification link.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email verified successfully.',
        ]);
    }
}
