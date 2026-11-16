<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatMessage;
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
            ->with('user')
            ->latest('id')
            ->take(50)
            ->get()
            ->reverse()
            ->values();

        return view('chat.index', ['messages' => $messages]);
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
            ],
        ], 201);
    }
}
