<?php

namespace Tests\Feature\Reviews;

use App\Enums\TicketStatus;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requester = User::factory()->create(['name' => 'Rita Requester']);
        $this->technician = User::factory()->technician()->create();
    }

    private function ticket(TicketStatus $status = TicketStatus::Resolved, ?User $owner = null): Ticket
    {
        return Ticket::factory()->for($owner ?? $this->requester, 'requester')->assignedTo($this->technician)->create(['status' => $status]);
    }

    // Valid submission

    public function test_requester_reviews_a_resolved_ticket(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->requester)->get("/tickets/{$ticket->id}")
            ->assertOk()->assertSee('Your feedback')->assertSee('Submit review')->assertSee('Confirm fix and close');

        $this->actingAs($this->requester)
            ->post("/tickets/{$ticket->id}/review", ['rating' => 4, 'comment' => 'Quick and friendly.'])
            ->assertRedirect("/tickets/{$ticket->id}")
            ->assertSessionHas('success', 'Thank you for your feedback.');

        $review = $ticket->review()->sole();
        $this->assertSame(4, $review->rating);
        $this->assertSame('Quick and friendly.', $review->comment);
        $this->assertSame($this->requester->id, $review->user_id);
        $this->assertNotNull($review->created_at);

        $this->actingAs($this->requester)->get("/tickets/{$ticket->id}")
            ->assertSee('Quick and friendly.')->assertSee('4/5')->assertSee('Edit your review')->assertDontSee('Submit review');
    }

    public function test_closed_tickets_can_be_reviewed_and_the_comment_is_optional(): void
    {
        $ticket = $this->ticket(TicketStatus::Closed);

        $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/review", ['rating' => 1])->assertSessionHasNoErrors();

        $this->assertSame(1, $ticket->review->rating);
        $this->assertNull($ticket->review->comment);
    }

    public function test_ticket_ownership_and_rating_cannot_be_forged(): void
    {
        $ticket = $this->ticket();
        $other = $this->ticket(owner: User::factory()->create());

        $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/review", [
            'rating' => 5,
            'ticket_id' => $other->id,
            'user_id' => $other->user_id,
        ])->assertSessionHasNoErrors();

        $review = Review::sole();
        $this->assertSame($ticket->id, $review->ticket_id);
        $this->assertSame($this->requester->id, $review->user_id);
    }

    // Rating range

    public function test_rating_must_be_a_whole_number_from_one_to_five(): void
    {
        $ticket = $this->ticket();

        foreach ([null, 0, 6, -1, 3.5, 'five', '4abc'] as $rating) {
            $this->actingAs($this->requester)
                ->from("/tickets/{$ticket->id}")
                ->post("/tickets/{$ticket->id}/review", ['rating' => $rating])
                ->assertRedirect("/tickets/{$ticket->id}")
                ->assertSessionHasErrorsIn('review', ['rating' => 'Choose a rating from 1 to 5 stars.']);
        }

        $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/review", ['rating' => 3, 'comment' => str_repeat('a', 1001)])
            ->assertSessionHasErrorsIn('review', 'comment');

        $this->assertSame(0, Review::count());
    }

    // Duplicates

    public function test_a_ticket_gets_only_one_review(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/review", ['rating' => 5])->assertSessionHasNoErrors();
        $this->actingAs($this->requester)->post("/tickets/{$ticket->id}/review", ['rating' => 1])
            ->assertSessionHasErrorsIn('review', ['rating' => 'You have already reviewed this ticket. You can edit your review instead.']);

        $this->assertSame(1, Review::count());
        $this->assertSame(5, $ticket->review->rating);
    }

    public function test_database_rejects_a_second_review_for_the_same_ticket(): void
    {
        $review = Review::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Review::factory()->create(['ticket_id' => $review->ticket_id, 'user_id' => $review->user_id]);
    }

    // Ownership

    public function test_only_the_requester_can_review(): void
    {
        $ticket = $this->ticket();
        $admin = User::factory()->admin()->create();

        foreach ([User::factory()->create(), $this->technician, $admin] as $actor) {
            $this->actingAs($actor)->post("/tickets/{$ticket->id}/review", ['rating' => 5])->assertForbidden();
        }

        $this->assertSame(0, Review::count());

        $this->expectException(ValidationException::class);
        app(TicketService::class)->submitReview($ticket, $admin, 5, null);
    }

    public function test_guests_cannot_review(): void
    {
        $ticket = $this->ticket();

        $this->post("/tickets/{$ticket->id}/review", ['rating' => 5])->assertRedirect('/login');
        $this->put("/tickets/{$ticket->id}/review", ['rating' => 5])->assertRedirect('/login');
        $this->assertSame(0, Review::count());
    }

    public function test_requester_edits_their_own_review(): void
    {
        $ticket = $this->ticket();
        $review = Review::factory()->for($ticket)->for($this->requester)->create(['rating' => 2, 'comment' => 'Slow.']);

        $this->travel(5)->minutes();

        $this->actingAs($this->requester)
            ->put("/tickets/{$ticket->id}/review", ['rating' => 4, 'comment' => 'It has stayed fixed, thanks.'])
            ->assertRedirect("/tickets/{$ticket->id}")
            ->assertSessionHas('success', 'Your review has been updated.');

        $review->refresh();
        $this->assertSame(4, $review->rating);
        $this->assertSame('It has stayed fixed, thanks.', $review->comment);
        $this->assertTrue($review->updated_at->gt($review->created_at));

        $this->actingAs($this->requester)->put("/tickets/{$ticket->id}/review", ['rating' => 9])
            ->assertSessionHasErrorsIn('review', 'rating');
        $this->assertSame(4, $review->fresh()->rating);
    }

    public function test_nobody_can_edit_another_users_review(): void
    {
        $ticket = $this->ticket();
        $review = Review::factory()->for($ticket)->for($this->requester)->create(['rating' => 5, 'comment' => 'Great']);

        foreach ([User::factory()->create(), $this->technician, User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->put("/tickets/{$ticket->id}/review", ['rating' => 1, 'comment' => 'Hacked'])->assertForbidden();
        }

        $this->assertSame(5, $review->fresh()->rating);
        $this->assertSame('Great', $review->fresh()->comment);

        // The requester's own ticket route can't be used to reach someone else's review either.
        $mine = $this->ticket();
        $this->actingAs($this->requester)->put("/tickets/{$mine->id}/review", ['rating' => 1])->assertForbidden();

        $this->expectException(ValidationException::class);
        app(TicketService::class)->updateReview($review, User::factory()->create(), 1, null);
    }

    // Unresolved tickets

    public function test_unresolved_tickets_cannot_be_reviewed(): void
    {
        foreach ([TicketStatus::Open, TicketStatus::Assigned, TicketStatus::InProgress] as $status) {
            $ticket = $this->ticket($status);

            $this->actingAs($this->requester)->get("/tickets/{$ticket->id}")
                ->assertOk()->assertDontSee('Submit review')->assertDontSee('Confirm fix and close');

            $this->actingAs($this->requester)
                ->from("/tickets/{$ticket->id}")
                ->post("/tickets/{$ticket->id}/review", ['rating' => 5])
                ->assertRedirect("/tickets/{$ticket->id}")
                ->assertSessionHasErrorsIn('review', ['rating' => 'You can review this ticket once it has been resolved.']);
        }

        $this->assertSame(0, Review::count());
    }

    // Requester closes a resolved ticket

    public function test_requester_confirms_the_fix_by_closing_a_resolved_ticket(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->requester)->put("/tickets/{$ticket->id}/close")
            ->assertRedirect("/tickets/{$ticket->id}")
            ->assertSessionHas('success', "{$ticket->reference} is now closed. Thank you for confirming.");

        $ticket->refresh();
        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertNotNull($ticket->closed_at);

        $activity = $ticket->activities()->sole();
        $this->assertSame(TicketActivity::ACTION_STATUS_CHANGED, $activity->action);
        $this->assertSame($this->requester->id, $activity->user_id);
        $this->assertSame(TicketStatus::Resolved, $activity->from_status);
        $this->assertSame(TicketStatus::Closed, $activity->to_status);
    }

    public function test_a_technician_who_submitted_a_ticket_can_close_it_as_requester(): void
    {
        $ownTicket = $this->ticket(owner: $otherTech = User::factory()->technician()->create());

        $this->actingAs($otherTech)->put("/tickets/{$ownTicket->id}/close")->assertSessionHasNoErrors();

        $this->assertSame(TicketStatus::Closed, $ownTicket->fresh()->status);
    }

    public function test_only_the_requester_closes_and_only_resolved_tickets(): void
    {
        $ticket = $this->ticket();

        foreach ([User::factory()->create(), $this->technician, User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->put("/tickets/{$ticket->id}/close")->assertForbidden();
        }
        $this->assertSame(TicketStatus::Resolved, $ticket->fresh()->status);

        foreach ([TicketStatus::Open, TicketStatus::Assigned, TicketStatus::InProgress, TicketStatus::Closed] as $status) {
            $unresolved = $this->ticket($status);

            $this->actingAs($this->requester)->put("/tickets/{$unresolved->id}/close")
                ->assertSessionHasErrorsIn('status', 'status');

            $this->assertSame($status, $unresolved->fresh()->status);
        }
    }

    public function test_requesters_still_cannot_use_the_status_forms(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->requester)->put("/admin/tickets/{$ticket->id}/status", ['status' => 'closed'])->assertForbidden();
        $this->actingAs($this->requester)->put("/technician/tickets/{$ticket->id}/status", ['status' => 'closed'])->assertForbidden();
        $this->assertSame(TicketStatus::Resolved, $ticket->fresh()->status);
    }
}
