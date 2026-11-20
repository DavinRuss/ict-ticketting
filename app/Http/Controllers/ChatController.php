<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ChatController extends Controller
{
    /**
     * The shared room. Only technicians and admins can reach this route (role
     * middleware), so no further scoping is needed here.
     */
    public function index(): View
    {
        $messages = ChatMessage::query()
            ->with(['user', 'reactions'])
            ->latest('id')
            ->take(50)
            ->get()
            ->reverse()
            ->values();

        $staff = User::query()
            ->whereIn('role', [UserRole::Technician, UserRole::Admin])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('chat.index', ['messages' => $messages, 'staff' => $staff]);
    }

    public function store(StoreChatMessageRequest $request, ChatService $chat): JsonResponse
    {
        $message = $chat->send($request->user(), $request->validated('body'));

        return response()->json([
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at->toISOString(),
                'author' => [
                    'id' => $message->user_id,
                    'name' => $message->user->name,
                    'role' => $message->user->role->value,
                ],
                'likes' => $message->likesCount(),
                'liked' => false,
            ],
        ], 201);
    }

    /**
     * Toggle the signed-in user's thumbs-up on a message.
     */
    public function react(ChatMessage $message, ChatService $chat): JsonResponse
    {
        $result = $chat->toggleLike($message, request()->user());

        return response()->json([
            'message_id' => $message->id,
            'likes' => $result['likes'],
            'liked' => $result['liked'],
        ]);
    }
}
