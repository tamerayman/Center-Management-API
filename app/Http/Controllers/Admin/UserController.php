<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminStoreUserRequest;
use App\Http\Requests\AdminUpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * كل المستخدمين — مع فلتر الدور والبحث بالاسم أو الإيميل.
     */
    public function index(Request $request)
    {
        $query = User::query()->with('profile')->latest();

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('profile', fn ($p) => $p->where('phone', 'like', "%{$search}%"));
            });
        }

        return UserResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(AdminStoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json([
            'message' => 'User created successfully.',
            'data' => new UserResource($user),
        ], 201);
    }

    public function show(User $user)
    {
        $user->load('profile');

        $statistics = [
            'courses_count' => $user->isTeacher() ? $user->courses()->count() : $user->enrolledCourses()->count(),
            'results_count' => $user->results()->count(),
            'attendances_count' => $user->attendances()->count(),
        ];

        return (new UserResource($user))->additional([
            'statistics' => $statistics,
        ]);
    }

    public function update(AdminUpdateUserRequest $request, User $user)
    {
        // المدير مش يقدر يغير دوره بنفسه — عشان مفيش حد يقفل نفسه بره النظام
        if ($user->id === $request->user()->id
            && $request->filled('role')
            && $request->validated('role') !== 'admin') {
            return response()->json([
                'message' => 'You cannot change your own role.',
            ], 400);
        }

        $user->update($request->validated());

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 400);
        }

        // كورسات المدرس بتتمسح معاه (والدروس والامتحانات معاهم عبر الـ cascade)
        if ($user->isTeacher()) {
            $user->courses()->delete();
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }
}
