<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ChatMessageNotification extends Notification implements ShouldBroadcast
{
    public function __construct(public ChatMessage $message)
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
        $author = $this->message->user;

        return [
            'title' => __('New team chat message'),
            'message' => __(':name: :preview', [
                'name' => $author?->name ?? __('Someone'),
                'preview' => Str::limit($this->message->body, 80),
            ]),
            'url' => route('chat.index'),
        ];
    }
}
