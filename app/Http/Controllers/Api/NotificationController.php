<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** List all notifications for current user, newest first */
    public function index(): JsonResponse
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($notifications);
    }

    /** Unread count for badge */
    public function unreadCount(): JsonResponse
    {
        $count = Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /** Mark one notification as read */
    public function markRead(string $id): JsonResponse
    {
        Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** Mark all as read */
    public function markAllRead(): JsonResponse
    {
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** Delete a notification */
    public function destroy(string $id): JsonResponse
    {
        Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json(['ok' => true]);
    }
}
