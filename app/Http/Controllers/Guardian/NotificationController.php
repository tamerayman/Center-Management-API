<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuardianNotificationResource;
use App\Models\GuardianNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $guardian = $request->user('guardian');

        $query = $guardian->notifications()->with('student')->latest();

        if ($request->boolean('unread')) {
            $query->unread();
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }
// or       $query->when($request->filled('student_id'), 
//     fn($q) => $q->where('student_id', $request->query('student_id'))
// );

        return GuardianNotificationResource::collection($query->paginate($this->perPage($request)));
    }

    public function markAsRead(Request $request, GuardianNotification $notification)
    {
        $guardian = $request->user('guardian');

        if ($notification->guardian_id !== $guardian->id) {
            return response()->json([
                'message' => 'This notification does not belong to your account.',
            ], 403);
        }

        $notification->update(['is_read' => true]);

        return new GuardianNotificationResource($notification->load('student'));
    }

    public function markAllAsRead(Request $request)
    {
        $request->user('guardian')->notifications()
            ->unread()
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
