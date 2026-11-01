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

    /**
     * Requester view (/tickets/{ticket}): only the owner.
     */
    public function view(User $actor, Ticket $ticket): bool
    {
        return $actor->id === $ticket->user_id;
    }

    public function create(User $actor): bool
    {
        return $actor->is_active;
    }

    /**
     * Admin request management: the list of every ticket and any ticket's details.
     */
    public function manage(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function assign(User $actor, Ticket $ticket): bool
    {
        return $actor->isAdmin();
    }

    public function changeStatus(User $actor, Ticket $ticket): bool
    {
        return $actor->isAdmin();
    }
}
