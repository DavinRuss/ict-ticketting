<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_message_id', 'user_id'])]
class ChatMessageReaction extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
