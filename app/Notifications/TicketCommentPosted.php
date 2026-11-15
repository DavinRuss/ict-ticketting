<?php

namespace App\Notifications;

use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Stored in the notifications table; open browser tabs pick it up from
 * NotificationController::feed() and show it as a desktop notification.
 */
class TicketCommentPosted extends Notification
{
    public function __construct(public TicketComment $comment) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $ticket = $this->comment->ticket;

        return [
            'ticket_id' => $ticket->id,
            'comment_id' => $this->comment->id,
            'title' => __('New comment on :reference', ['reference' => $ticket->reference]),
            'body' => __(':name: :comment', ['name' => $this->comment->user->name, 'comment' => Str::limit($this->comment->body, 120)]),
            'url' => $ticket->urlFor($notifiable).'#comments',
        ];
    }
}
