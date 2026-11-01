<?php

namespace App\Policies;

use App\Enums\UserRole;
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

    /**
     * Technician workspace (/technician/tickets/{ticket}): only the technician the ticket is assigned to now.
     */
    public function work(User $actor, Ticket $ticket): bool
    {
        return $actor->hasRole(UserRole::Technician) && $ticket->assigned_to === $actor->id;
    }

    public function addWorkNote(User $actor, Ticket $ticket): bool
    {
        return $this->work($actor, $ticket);
    }

    /**
     * The requester confirms a resolved ticket by closing it; TicketService checks the status.
     */
    public function close(User $actor, Ticket $ticket): bool
    {
        return $this->view($actor, $ticket);
    }

    /**
     * Which statuses each role may set is decided by TicketStatus::transitionsFor().
     */
    public function changeStatus(User $actor, Ticket $ticket): bool
    {
        return $actor->isAdmin() || $this->work($actor, $ticket);
    }
}
