<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for managing in-app notifications for the authenticated user.
 */
class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     *
     * @return JsonResponse Paginated notifications with unread count.
     */
    public function index(Request $request)
    {
        $query = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        // Optional filter to show only unread notifications
        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $paginated = $query->paginate(30);
        $userId = $request->user()->id;

        // Calculate the total unread count for the UI badge
        $unreadCount = AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => $paginated->items(),
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Get the count of unread notifications for the current user.
     *
     * @return JsonResponse
     */
    public function unreadCount(Request $request)
    {
        $count = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a specific notification as read.
     *
     * @return JsonResponse The updated notification.
     */
    public function markRead(Request $request, AppNotification $notification)
    {
        // Authorization: ensure the notification belongs to the requester
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json($notification->fresh());
    }

    /**
     * Mark all notifications for the current user as read.
     *
     * @return JsonResponse 200 OK message.
     */
    public function markAllRead(Request $request)
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications ont été lues.']);
    }
}
