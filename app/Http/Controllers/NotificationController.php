<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The signed-in user's own notifications only: every query starts from $request->user().
 * Comment previews are only shown while the user can still see the ticket
 * (TicketPolicy::comment), so reassignment or a role change hides them.
 */
class NotificationController extends Controller
{
    public const FEED_LIMIT = 10;

    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(15);

        return view('notifications.index', [
            'notifications' => $notifications,
            'accessibleTicketIds' => $this->accessibleTicketIds($request->user(), $notifications->getCollection()),
        ]);
    }

    /**
     * Polled by resources/js/desktop-notifications.js to show desktop notifications and the unread badge.
     * Unread notifications for tickets the user can no longer see are marked read instead of shown.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $unread = $user->unreadNotifications()->limit(self::FEED_LIMIT)->get();
        $accessible = $this->accessibleTicketIds($user, $unread);
        [$visible, $revoked] = $unread->partition(fn (DatabaseNotification $notification) => in_array($notification->data['ticket_id'] ?? null, $accessible, true));

        if ($revoked->isNotEmpty()) {
            $user->unreadNotifications()->whereKey($revoked->modelKeys())->update(['read_at' => now()]);
        }

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $visible->values()->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? '',
                'body' => $notification->data['body'] ?? '',
                'url' => route('notifications.open', $notification->id),
                'created_at' => $notification->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Mark one notification read and go to the ticket, on the page the user's current role uses.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $ticket = Ticket::find($notification->data['ticket_id'] ?? null);

        if (! $ticket || $user->cannot('comment', $ticket)) {
            return redirect()->route('notifications.index')->with('error', __('You no longer have access to this ticket.'));
        }

        return redirect()->to($ticket->urlFor($user).'#comments');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('success', __('All notifications marked as read.'));
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return list<int>
     */
    private function accessibleTicketIds(User $user, Collection $notifications): array
    {
        $ids = $notifications->pluck('data.ticket_id')->filter()->unique()->all();

        return Ticket::query()->whereKey($ids)->get()
            ->filter(fn (Ticket $ticket) => $user->can('comment', $ticket))
            ->modelKeys();
    }
}
