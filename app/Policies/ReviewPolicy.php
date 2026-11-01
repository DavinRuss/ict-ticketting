<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\Ticket;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Admin feedback list.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Only the ticket's requester reviews it; TicketService checks the status and duplicates.
     */
    public function create(User $actor, Ticket $ticket): bool
    {
        return $actor->id === $ticket->user_id;
    }

    public function update(User $actor, Review $review): bool
    {
        return $actor->id === $review->user_id;
    }
}
