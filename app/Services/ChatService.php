<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ChatService
{
    /**
     * Persist a chat message from a technician or admin and push it to the room.
     *
     * The route group already restricts access by role; this re-checks the actor
     * so the rule cannot be bypassed when the service is called directly.
     */
    public function send(User $actor, string $body): ChatMessage
    {
        if (! $actor->is_active || ! $actor->hasRole(UserRole::Technician, UserRole::Admin)) {
            throw ValidationException::withMessages([
                'body' => __('Only active technicians and admins can send messages.'),
            ]);
        }

        $message = new ChatMessage(['body' => $body]);
        $message->user()->associate($actor);
        $message->save();

        $message->load('user');

        try {
            ChatMessageSent::dispatch($message);
        } catch (\Throwable $e) {
            // A message must never be lost just because the WebSocket server is
            // unreachable; it is still persisted and shown on the next page load.
            report($e);
        }

        $recipients = User::query()
            ->whereIn('role', [UserRole::Technician, UserRole::Admin])
            ->where('is_active', true)
            ->whereKeyNot($actor->getKey())
            ->get();

        Notification::send($recipients, new ChatMessageNotification($message));

        return $message;
    }
}
