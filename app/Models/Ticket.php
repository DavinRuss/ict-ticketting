<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Status, requester and assignment fields are deliberately not fillable:
 * they are only set by TicketService (or later workflow code), never from request input.
 */
#[Fillable(['title', 'description', 'category_id', 'priority'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'open',
        'priority' => 'medium',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Built from the auto-increment id after insert, so two tickets can never share a reference.
        static::created(function (Ticket $ticket) {
            $ticket->forceFill(['reference' => self::referenceFor($ticket->id)])->saveQuietly();
        });
    }

    public static function referenceFor(int $id): string
    {
        return 'TKT-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * The page this user works on the ticket from: admin, technician workspace or requester view.
     */
    public function urlFor(User $user): string
    {
        return match (true) {
            $user->isAdmin() => route('admin.tickets.show', $this),
            $user->hasRole(UserRole::Technician) && $this->assigned_to === $user->id => route('technician.tickets.show', $this),
            default => route('tickets.show', $this),
        };
    }
}
