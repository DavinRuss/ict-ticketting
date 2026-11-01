<?php

namespace Tests\Feature\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $ticket = Ticket::factory()->create();

        $this->get('/tickets')->assertRedirect('/login');
        $this->get("/tickets/{$ticket->id}")->assertRedirect('/login');
    }

    public function test_list_shows_only_the_users_own_tickets(): void
    {
        $user = User::factory()->create();
        Ticket::factory()->for($user, 'requester')->create(['title' => 'My printer is broken']);
        Ticket::factory()->create(['title' => 'Someone else problem']);

        $this->actingAs($user)->get('/tickets')
            ->assertOk()
            ->assertSee('My printer is broken')
            ->assertDontSee('Someone else problem');
    }

    public function test_owner_sees_ticket_details_and_assigned_technician(): void
    {
        $user = User::factory()->create();
        $technician = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $ticket = Ticket::factory()->for($user, 'requester')->assignedTo($technician)->create();

        $this->actingAs($user)->get("/tickets/{$ticket->id}")
            ->assertOk()
            ->assertSee($ticket->reference)
            ->assertSee($ticket->title)
            ->assertSee('Assigned')
            ->assertSee('Tina Tech');
    }

    public function test_unassigned_ticket_says_so(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->for($user, 'requester')->create();

        $this->actingAs($user)->get("/tickets/{$ticket->id}")->assertOk()->assertSee('Not assigned yet');
    }

    public function test_users_cannot_open_another_users_ticket_by_changing_the_url(): void
    {
        $ticket = Ticket::factory()->create(['title' => 'Private problem']);

        $this->actingAs(User::factory()->create())
            ->get("/tickets/{$ticket->id}")
            ->assertForbidden()
            ->assertDontSee('Private problem');
    }

    public function test_missing_ticket_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())->get('/tickets/999999')->assertNotFound();
    }

    public function test_tickets_cannot_be_edited_or_deleted_through_this_area(): void
    {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->for($user, 'requester')->create();

        $this->actingAs($user)->patch("/tickets/{$ticket->id}", ['status' => 'closed'])->assertMethodNotAllowed();
        $this->actingAs($user)->delete("/tickets/{$ticket->id}")->assertMethodNotAllowed();
        $this->assertSame('open', $ticket->fresh()->status->value);
    }

    public function test_list_is_paginated_fifteen_per_page(): void
    {
        $user = User::factory()->create();
        Ticket::factory()->for($user, 'requester')->count(16)->create();

        $this->actingAs($user)->get('/tickets')->assertOk()->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 15 && $tickets->total() === 16);
        $this->actingAs($user)->get('/tickets?page=2')->assertOk()->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 1);
    }

    public function test_list_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        Ticket::factory()->for($user, 'requester')->create(['title' => 'Still open']);
        Ticket::factory()->for($user, 'requester')->assignedTo(User::factory()->technician()->create())->create(['title' => 'Already assigned']);

        $this->actingAs($user)->get('/tickets?status=assigned')
            ->assertOk()
            ->assertSee('Already assigned')
            ->assertDontSee('Still open');

        $this->actingAs($user)->get('/tickets?status=bogus')->assertSessionHasErrors('status');
    }

    public function test_empty_states_guide_the_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/tickets')->assertOk()->assertSee('You have not submitted any tickets yet.')->assertSee('Submit your first ticket');
        $this->actingAs($user)->get('/tickets?status=closed')->assertOk()->assertSee('You have no tickets with this status.');
    }

    public function test_dashboard_counts_only_the_users_own_tickets(): void
    {
        $user = User::factory()->create();
        Ticket::factory()->for($user, 'requester')->count(2)->create(['title' => 'Mine']);
        Ticket::factory()->count(3)->create(['title' => 'Not mine']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('statusCounts', fn ($counts) => $counts['open'] === 2 && $counts->sum() === 2)
            ->assertSee('Mine')
            ->assertDontSee('Not mine');
    }
}
