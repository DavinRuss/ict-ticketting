<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail of ticket changes. Rows are never updated.
 */
#[Fillable(['user_id', 'action', 'from_status', 'to_status', 'note'])]
class TicketActivity extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_CREATED = 'created';

    protected function casts(): array
    {
        return [
            'from_status' => TicketStatus::class,
            'to_status' => TicketStatus::class,
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function description(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => __('Ticket submitted'),
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
