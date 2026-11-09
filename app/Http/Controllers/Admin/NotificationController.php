<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * إرسال إشعار لمستخدم معين.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        Notification::create([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'message' => $validated['message'],
        ]);

        return response()->json([
            'message' => 'Notification sent successfully.',
        ], 201);
    }

    /**
     * بث إشعار لكل المستخدمين أو لدور معين (admin / teacher / student).
     */
    public function broadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'in:admin,teacher,student'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $query = User::query();

        if (! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        $now = now();
        $chunkSize = 500;

        $sent = 0;

        $query->select('id')->chunkById($chunkSize, function ($users) use ($validated, $now, &$sent) {
            $rows = $users->map(fn ($user) => [
                'user_id' => $user->id,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            Notification::insert($rows);
            $sent += count($rows);
        });

        return response()->json([
            'message' => 'Broadcast sent successfully.',
            'recipients' => $sent,
        ], 201);
    }
}
