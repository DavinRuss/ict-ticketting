<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * Assign (or reassign) a ticket to an active technician and log who did it.
     *
     * The ticket row is locked and re-read, so the checks run against its current
     * state even if someone else changed it after the page was loaded.
     */
    public function assign(Ticket $ticket, User $technician, User $actor, ?string $note = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $technician, $actor, $note) {
            $ticket = Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $technician = User::query()->whereKey($technician->getKey())->lockForUpdate()->firstOrFail();

            if (! $technician->hasRole(UserRole::Technician) || ! $technician->is_active) {
                throw $this->rejected('assignment', 'technician_id', __('Tickets can only be assigned to an active technician.'));
            }

            $from = $ticket->status;

            if (! $from->canTransitionTo(TicketStatus::Assigned)) {
                throw $this->rejected('assignment', 'technician_id', __('A ticket that is :status cannot be assigned or reassigned.', ['status' => strtolower($from->label())]));
            }

            if ($ticket->assigned_to === $technician->id) {
                throw $this->rejected('assignment', 'technician_id', __('This ticket is already assigned to :name.', ['name' => $technician->name]));
            }

            $previousTechnicianId = $ticket->assigned_to;

            $ticket->forceFill([
                'status' => TicketStatus::Assigned,
                'assigned_to' => $technician->id,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ])->save();

            $ticket->activities()->create([
                'user_id' => $actor->id,
                'action' => $previousTechnicianId ? TicketActivity::ACTION_REASSIGNED : TicketActivity::ACTION_ASSIGNED,
                'from_status' => $from,
                'to_status' => TicketStatus::Assigned,
                'from_technician_id' => $previousTechnicianId,
                'to_technician_id' => $technician->id,
                'note' => $note,
            ]);

            return $ticket;
        });
    }

    /**
     * Move a ticket to the next status allowed by TicketStatus and log who did it.
     * Assigning goes through assign(), because it also needs a technician.
     */
    public function changeStatus(Ticket $ticket, TicketStatus $to, User $actor, ?string $note = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $to, $actor, $note) {
            $ticket = Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $from = $ticket->status;

            if ($to === TicketStatus::Assigned) {
                throw $this->rejected('status', 'status', __('Use the assignment form to assign or reassign a technician.'));
            }

            if (! $from->canTransitionTo($to)) {
                throw $this->rejected('status', 'status', __('A ticket cannot move from :from to :to.', ['from' => $from->label(), 'to' => $to->label()]));
            }

            $ticket->status = $to;

            match ($to) {
                TicketStatus::InProgress => $ticket->started_at ??= now(),
                TicketStatus::Resolved => $ticket->resolved_at = now(),
                TicketStatus::Closed => $ticket->closed_at = now(),
                default => null,
            };

            $ticket->save();

            $ticket->activities()->create([
                'user_id' => $actor->id,
                'action' => TicketActivity::ACTION_STATUS_CHANGED,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
            ]);

            return $ticket;
        });
    }

    private function rejected(string $errorBag, string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => $message])->errorBag($errorBag);
    }
}
