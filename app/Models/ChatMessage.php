<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['body'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class);
    }

    public function likesCount(): int
    {
        return $this->reactions_count ?? $this->reactions()->count();
    }
}
