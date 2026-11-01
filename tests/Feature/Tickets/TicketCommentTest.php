<?php

namespace Tests\Feature\Tickets;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_factory_uses_the_ticket_requester_as_author(): void
    {
        $comment = TicketComment::factory()->create();

        $this->assertSame($comment->ticket->user_id, $comment->user_id);
    }

    public function test_requester_can_comment_and_comment_is_escaped_for_every_ticket_viewer(): void
    {
        $requester = User::factory()->create();
        $technician = User::factory()->technician()->create();
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->for($requester, 'requester')->assignedTo($technician)->create();
        $body = 'The printer still jams. <script>alert(1)</script>';

        $this->actingAs($requester)->post("/tickets/{$ticket->id}/comments", [
            'body' => $body,
            'ticket_id' => 999999,
            'user_id' => $admin->id,
        ])->assertRedirect("/tickets/{$ticket->id}")->assertSessionHas('success', 'Comment added.');

        $comment = TicketComment::sole();
        $this->assertSame($ticket->id, $comment->ticket_id);
        $this->assertSame($requester->id, $comment->user_id);
        $this->assertSame($body, $comment->body);

        $this->actingAs($requester)->get("/tickets/{$ticket->id}")
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->actingAs($technician)->get("/technician/tickets/{$ticket->id}")->assertOk()->assertSee('The printer still jams.');
        $this->actingAs($admin)->get("/admin/tickets/{$ticket->id}")->assertOk()->assertSee('The printer still jams.');
    }

    public function test_only_the_ticket_requester_can_comment(): void
    {
        $requester = User::factory()->create();
        $ticket = Ticket::factory()->for($requester, 'requester')->create();

        foreach ([User::factory()->create(), User::factory()->technician()->create(), User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->post("/tickets/{$ticket->id}/comments", ['body' => 'Private update'])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_comment_body_is_required_and_limited_to_five_thousand_characters(): void
    {
        $requester = User::factory()->create();
        $ticket = Ticket::factory()->for($requester, 'requester')->create();

        $this->actingAs($requester)->post("/tickets/{$ticket->id}/comments", [])->assertSessionHasErrorsIn('comment', 'body');
        $this->actingAs($requester)->post("/tickets/{$ticket->id}/comments", ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrorsIn('comment', 'body');

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_comments_and_visible_activity_are_paginated_on_ticket_pages(): void
    {
        $requester = User::factory()->create();
        $technician = User::factory()->technician()->create();
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->for($requester, 'requester')->assignedTo($technician)->create();

        foreach (range(1, 16) as $number) {
            $comment = new TicketComment(['body' => "Comment {$number}"]);
            $comment->user()->associate($requester);
            $ticket->comments()->save($comment);
            $ticket->activities()->create([
                'user_id' => $requester->id,
                'action' => TicketActivity::ACTION_CREATED,
                'to_status' => 'open',
            ]);
        }

        $this->actingAs($requester)->get("/tickets/{$ticket->id}?comments_page=2&activities_page=2")
            ->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->count() === 1 && $comments->total() === 16)
            ->assertViewHas('activities', fn ($activities) => $activities->count() === 1 && $activities->total() === 16);
        $this->actingAs($technician)->get("/technician/tickets/{$ticket->id}?comments_page=2&activities_page=2")
            ->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->count() === 1)
            ->assertViewHas('activities', fn ($activities) => $activities->count() === 1);
        $this->actingAs($admin)->get("/admin/tickets/{$ticket->id}?comments_page=2&activities_page=2")
            ->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->count() === 1)
            ->assertViewHas('activities', fn ($activities) => $activities->count() === 1);
    }
}
