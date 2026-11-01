<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail of ticket changes. Rows are never updated.
 */
#[Fillable(['user_id', 'action', 'from_status', 'to_status', 'from_technician_id', 'to_technician_id', 'note'])]
class TicketActivity extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_CREATED = 'created';

    public const ACTION_ASSIGNED = 'assigned';

    public const ACTION_REASSIGNED = 'reassigned';

    public const ACTION_STATUS_CHANGED = 'status_changed';

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

    public function fromTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_technician_id');
    }

    public function toTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_technician_id');
    }

    public function description(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => __('Ticket submitted'),
            self::ACTION_ASSIGNED => __('Assigned to :technician', ['technician' => $this->toTechnician?->name]),
            self::ACTION_REASSIGNED => __('Reassigned from :from to :to', ['from' => $this->fromTechnician?->name, 'to' => $this->toTechnician?->name]),
            self::ACTION_STATUS_CHANGED => __('Status changed from :from to :to', ['from' => $this->from_status?->label(), 'to' => $this->to_status?->label()]),
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
