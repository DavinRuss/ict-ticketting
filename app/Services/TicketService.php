<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
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
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();

            if (! $actor->isAdmin() || ! $actor->is_active) {
                throw $this->rejected('assignment', 'technician_id', __('Only an active admin can assign or reassign a technician.'));
            }

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
     * Move a ticket to the next status allowed by TicketStatus for the actor's role
     * (plus the requester's own rules when the actor submitted the ticket) and log who
     * did it. Assigning goes through assign(), because it also needs a technician.
     */
    public function changeStatus(Ticket $ticket, TicketStatus $to, User $actor, ?string $note = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $to, $actor, $note) {
            $ticket = Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $from = $ticket->status;
            $isRequester = $ticket->user_id === $actor->id;

            if (! $isRequester || $to !== TicketStatus::Closed) {
                $this->ensureStillAssigned($ticket, $actor, 'status', 'status');
            }

            if ($to === TicketStatus::Assigned) {
                throw $this->rejected('status', 'status', __('Only an admin can assign or reassign a technician, using the assignment form.'));
            }

            if (! $from->canTransitionTo($to)) {
                throw $this->rejected('status', 'status', __('A ticket cannot move from :from to :to.', ['from' => $from->label(), 'to' => $to->label()]));
            }

            $permitted = $from->transitionsFor($actor->role);

            if ($isRequester) {
                $permitted = [...$permitted, ...$from->transitionsFor(UserRole::User)];
            }

            if (! in_array($to, $permitted, true)) {
                throw $this->rejected('status', 'status', __('A :role cannot move a ticket to :to.', ['role' => strtolower($actor->role->label()), 'to' => $to->label()]));
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

    /**
     * Add an internal work note (hidden from the requester) to the ticket's history.
     */
    public function addWorkNote(Ticket $ticket, User $actor, string $note): TicketActivity
    {
        return DB::transaction(function () use ($ticket, $actor, $note) {
            $ticket = Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureStillAssigned($ticket, $actor, 'note', 'note');

            if ($ticket->status === TicketStatus::Closed) {
                throw $this->rejected('note', 'note', __('This ticket is closed. Work notes can no longer be added.'));
            }

            return $ticket->activities()->create([
                'user_id' => $actor->id,
                'action' => TicketActivity::ACTION_WORK_NOTE,
                'note' => $note,
            ]);
        });
    }

    /** Add a public comment from the ticket requester. */
    public function addComment(Ticket $ticket, User $requester, string $body): TicketComment
    {
        return DB::transaction(function () use ($ticket, $requester, $body) {
            $ticket = Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            if ($ticket->user_id !== $requester->id) {
                throw $this->rejected('comment', 'body', __('Only the person who submitted this ticket can comment.'));
            }

            $comment = new TicketComment(['body' => $body]);
            $comment->ticket()->associate($ticket);
            $comment->user()->associate($requester);
            $comment->save();

            return $comment;
        });
    }

    /**
     * Record the requester's satisfaction review. One per ticket, only once the
     * ticket is resolved or closed; the unique index on reviews.ticket_id backs this up.
     */
    public function submitReview(Ticket $ticket, User $requester, int $rating, ?string $comment): Review
    {
        try {
            return DB::transaction(function () use ($ticket, $requester, $rating, $comment) {
                $ticket = Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

                if ($ticket->user_id !== $requester->id) {
                    throw $this->rejected('review', 'rating', __('Only the person who submitted this ticket can review it.'));
                }

                if (! $ticket->status->acceptsReviews()) {
                    throw $this->rejected('review', 'rating', __('You can review this ticket once it has been resolved.'));
                }

                if ($ticket->review()->exists()) {
                    throw $this->rejected('review', 'rating', __('You have already reviewed this ticket. You can edit your review instead.'));
                }

                $review = new Review(['rating' => $rating, 'comment' => $comment]);
                $review->ticket()->associate($ticket);
                $review->user()->associate($requester);
                $review->save();

                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->rejected('review', 'rating', __('You have already reviewed this ticket. You can edit your review instead.'));
        }
    }

    /**
     * Let the review's author change their rating or comment.
     */
    public function updateReview(Review $review, User $author, int $rating, ?string $comment): Review
    {
        return DB::transaction(function () use ($review, $author, $rating, $comment) {
            $review = Review::query()->whereKey($review->getKey())->lockForUpdate()->firstOrFail();

            if ($review->user_id !== $author->id) {
                throw $this->rejected('review', 'rating', __('You can only edit your own review.'));
            }

            $review->update(['rating' => $rating, 'comment' => $comment]);

            return $review;
        });
    }

    /**
     * A technician acts only on a ticket that is still assigned to them; it may have
     * been reassigned after their page loaded.
     */
    private function ensureStillAssigned(Ticket $ticket, User $actor, string $errorBag, string $field): void
    {
        if ($actor->hasRole(UserRole::Technician) && $ticket->assigned_to !== $actor->id) {
            throw $this->rejected($errorBag, $field, __('This ticket is no longer assigned to you.'));
        }
    }

    private function rejected(string $errorBag, string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([$field => $message])->errorBag($errorBag);
    }
}
