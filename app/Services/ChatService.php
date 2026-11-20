<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Events\ChatMessageLiked;
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

        $message->load('user')->loadCount('reactions');

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

    /**
     * Toggle the actor's thumbs-up on a message and broadcast the new total so
     * every open chat updates its counter. Same role rule as send().
     *
     * @return array{likes: int, liked: bool}
     */
    public function toggleLike(ChatMessage $message, User $actor): array
    {
        if (! $actor->is_active || ! $actor->hasRole(UserRole::Technician, UserRole::Admin)) {
            throw ValidationException::withMessages([
                'body' => __('Only active technicians and admins can react to messages.'),
            ]);
        }

        $existing = $message->reactions()->where('user_id', $actor->id)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $message->reactions()->create(['user_id' => $actor->id]);
            $liked = true;
        }

        $likes = $message->reactions()->count();

        try {
            ChatMessageLiked::dispatch($message->id, $likes);
        } catch (\Throwable $e) {
            report($e);
        }

        return ['likes' => $likes, 'liked' => $liked];
    }
}
