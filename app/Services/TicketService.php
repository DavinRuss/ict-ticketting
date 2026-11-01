<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketService
{
    /**
     * Open a new ticket for the requester and record it in the activity log.
     *
     * @param  array{title: string, description: string, category_id: int, priority: string}  $data
     */
    public function create(User $requester, array $data): Ticket
    {
        return DB::transaction(function () use ($requester, $data) {
            $ticket = new Ticket($data);
            $ticket->requester()->associate($requester);
            $ticket->status = TicketStatus::Open;
            $ticket->save();

            $ticket->activities()->create([
                'user_id' => $requester->id,
                'action' => TicketActivity::ACTION_CREATED,
                'to_status' => TicketStatus::Open,
            ]);

            return $ticket;
        });
    }
}
