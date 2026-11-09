<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => 'student',  // السجيل العام للطلاب فقط — المدرسين والأدمن عبر /admin/users
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        Notification::create([
            'user_id' => $user->id,
            'title'   => 'Welcome',
            'message' => 'Welcome to Center Management API.'
        ]);

        return response()->json([
            'message' => 'User registered successfully.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // يحذف التوكن الحالي فقط — لحذف كل الأجهزة: $request->user()->tokens()->delete()
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
