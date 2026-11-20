<?php

namespace Tests\Feature\Admin;

use App\Models\Review;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    }

    /**
     * A ticket with one activity (assignment), one comment and one review attached.
     */
    private function ticketWithHistory(): Ticket
    {
        $requester = User::factory()->create();
        $technician = User::factory()->technician()->create();

        $ticket = Ticket::factory()->for($requester, 'requester')->create();

        app(TicketService::class)->assign($ticket, $technician, $this->admin);

        $comment = new TicketComment(['body' => 'Please fix this soon.']);
        $comment->ticket()->associate($ticket);
        $comment->user()->associate($requester);
        $comment->save();

        $review = new Review(['rating' => 5, 'comment' => 'All good']);
        $review->ticket()->associate($ticket);
        $review->user()->associate($requester);
        $review->save();

        return $ticket->fresh();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $ticket = Ticket::factory()->create();

        $this->delete(route('admin.tickets.destroy', $ticket))->assertRedirect('/login');
    }

    public function test_users_and_technicians_cannot_erase_a_ticket(): void
    {
        $ticket = $this->ticketWithHistory();

        foreach ([User::factory()->create(), $ticket->technician] as $actor) {
            $this->actingAs($actor)
                ->delete(route('admin.tickets.destroy', $ticket))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
        $this->assertSame(1, $ticket->activities()->count());
        $this->assertSame(1, $ticket->comments()->count());
        $this->assertTrue($ticket->review()->exists());
    }

    public function test_admin_can_erase_a_ticket_and_everything_attached_to_it(): void
    {
        $ticket = $this->ticketWithHistory();
        $reference = $ticket->reference;

        $this->actingAs($this->admin)
            ->delete(route('admin.tickets.destroy', $ticket))
            ->assertRedirect(route('admin.tickets.index'))
            ->assertSessionHas('success', "Ticket {$reference} was erased.");

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
        $this->assertDatabaseMissing('ticket_activities', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseMissing('ticket_comments', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseMissing('reviews', ['ticket_id' => $ticket->id]);
    }

    public function test_erasing_a_missing_ticket_returns_not_found(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.tickets.destroy', 999999))
            ->assertNotFound();
    }

    public function test_service_rejects_erase_by_a_non_admin(): void
    {
        $ticket = $this->ticketWithHistory();

        try {
            app(TicketService::class)->delete($ticket, User::factory()->create());
            $this->fail('A non-admin must not erase tickets.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
        }
    }
}
