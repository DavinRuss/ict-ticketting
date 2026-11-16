<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldBroadcast
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
        $requester = $this->ticket->requester;

        return [
            'title' => __('New ticket submitted'),
            'message' => __(':reference by :name needs triage.', [
                'reference' => $this->ticket->reference,
                'name' => $requester?->name ?? __('a user'),
            ]),
            'url' => route('admin.tickets.show', $this->ticket),
        ];
    }
}
