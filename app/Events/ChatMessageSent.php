<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushed to the shared "chat" private channel so every connected technician and
 * admin receives the new message immediately. ShouldBroadcastNow keeps delivery
 * synchronous, so no queue worker is required on the LAN host.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public ChatMessage $message)
    {
        //
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat')];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Only the fields the client needs; never leak anything beyond the author's
     * name and role.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at->toISOString(),
            'author' => [
                'id' => $this->message->user_id,
                'name' => $this->message->user->name,
                'role' => $this->message->user->role->value,
            ],
        ];
    }
}
