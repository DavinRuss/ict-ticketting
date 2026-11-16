<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldBroadcast
{
    public function __construct(public Ticket $ticket)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge($this->payload(), [
            'id' => $this->id,
            'type' => static::class,
        ]);
    }

    public function broadcastAs(): string
    {
        return 'notification.received';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => __('Ticket assigned to you'),
            'message' => __(':reference — :title', [
                'reference' => $this->ticket->reference,
                'title' => $this->ticket->title,
            ]),
            'url' => route('technician.tickets.show', $this->ticket),
        ];
    }
}
