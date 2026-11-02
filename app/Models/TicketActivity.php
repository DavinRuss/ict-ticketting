<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Internal work notes: visible to the assigned technician and admins, never to the requester.
     */
    public const ACTION_WORK_NOTE = 'work_note';

    public const ACTIONS = [
        self::ACTION_CREATED,
        self::ACTION_ASSIGNED,
        self::ACTION_REASSIGNED,
        self::ACTION_STATUS_CHANGED,
        self::ACTION_WORK_NOTE,
    ];

    protected function casts(): array
    {
        return [
            'from_status' => TicketStatus::class,
            'to_status' => TicketStatus::class,
        ];
    }

    public function isInternal(): bool
    {
        return $this->action === self::ACTION_WORK_NOTE;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleToRequester(Builder $query): void
    {
        $query->where('action', '!=', self::ACTION_WORK_NOTE);
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
            self::ACTION_WORK_NOTE => __('Work note'),
            self::ACTION_STATUS_CHANGED => __('Status changed from :from to :to', ['from' => $this->from_status?->label(), 'to' => $this->to_status?->label()]),
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
