<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Exam;
use App\Models\Lesson;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
//استدعي ال hash
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);
        $token = $user->createToken('api-token')->plainTextToken;
        // Create a notification for the user about successful registration
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Welcome',
            'message' => 'Welcome to Center Management API.'
        ]);
        return response()->json([
            'message' => 'User registered successfully.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request)
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
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        // ده بيحذف التوكن الحالي فقط
        // $request->user()->tokens()->delete();
        // وده بيعمل Logout من كل الأجهزة.

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }


    }
