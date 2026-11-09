<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->notifications()->latest();

        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        return NotificationResource::collection($query->paginate($this->perPage($request)));
    }

    public function markAsRead(Notification $notification)
    {
        $this->authorize('update', $notification);

        $notification->update([
            'is_read' => true,
        ]);

        return new NotificationResource($notification);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
