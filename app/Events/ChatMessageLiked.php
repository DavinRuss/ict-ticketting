<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushed to the shared "chat" private channel whenever someone likes or unlikes
 * a message so every connected client can update the counter in real time.
 */
class ChatMessageLiked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $messageId, public int $likes)
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
        return 'message.liked';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'likes' => $this->likes,
        ];
    }
}
