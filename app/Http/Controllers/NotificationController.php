<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Latest notifications for the bell dropdown. Only the signed-in user's own
     * notifications are ever returned.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest('created_at')
            ->limit(15)
            ->get()
            ->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? __('Notification'),
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at->toISOString(),
            ]);

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse|JsonResponse
    {
        $this->authorizeOwnership($request->user(), $notification);

        $notification->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
        }

        return back();
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['unread_count' => 0]);
    }

    private function authorizeOwnership(User $user, DatabaseNotification $notification): void
    {
        abort_unless(
            $notification->notifiable_type === $user->getMorphClass()
                && $notification->notifiable_id === $user->getKey(),
            404,
        );
    }
}
