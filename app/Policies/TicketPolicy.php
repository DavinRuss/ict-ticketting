<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Every signed-in user can list their own tickets; the query is always scoped to them.
     */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Ticket $ticket): bool
    {
        return $actor->id === $ticket->user_id;
    }

    public function create(User $actor): bool
    {
        return $actor->is_active;
    }
}
