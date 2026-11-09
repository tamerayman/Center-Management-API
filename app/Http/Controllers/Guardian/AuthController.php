<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardianRegisterRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * ولي الأمر ينشئ حسابه بنفسه — الكود هو إثبات الارتباط بالطالب.
     */
    public function register(GuardianRegisterRequest $request): JsonResponse
    {
        $student = User::where('guardian_code', $request->guardian_code)
            ->where('role', 'student')
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'The guardian code is invalid.',
                'errors' => ['guardian_code' => ['The guardian code is invalid.']],
            ], 422);
        }

        $guardian = Guardian::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        GuardianStudent::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => $request->relationship,
        ]);

        $token = $guardian->createToken('guardian-token')->plainTextToken;

        return response()->json([
            'message' => 'Guardian registered successfully.',
            'guardian' => new GuardianResource($guardian),
            'children' => [[
                'id' => $student->id,
                'name' => $student->name,
                'relationship' => $request->relationship,
            ]],
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $guardian = Guardian::where('phone', $request->phone)->first();

        if (! $guardian || ! Hash::check($request->password, $guardian->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $guardian->createToken('guardian-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'guardian' => new GuardianResource($guardian),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('guardian')->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
