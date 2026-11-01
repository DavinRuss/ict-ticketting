<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCreationTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Laptop will not power on',
            'description' => 'Pressed the power button, nothing happens.',
            'category_id' => Category::factory()->create()->id,
            'priority' => 'high',
        ], $overrides);
    }

    public function test_guests_cannot_open_or_submit_the_form(): void
    {
        $this->get('/tickets/create')->assertRedirect('/login');
        $this->post('/tickets', $this->validData())->assertRedirect('/login');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_form_lists_only_active_categories(): void
    {
        Category::factory()->create(['name' => 'Hardware']);
        Category::factory()->inactive()->create(['name' => 'Retired category']);

        $this->actingAs(User::factory()->create())
            ->get('/tickets/create')
            ->assertOk()
            ->assertSee('Hardware')
            ->assertDontSee('Retired category');
    }

    public function test_user_submits_a_ticket_that_starts_open_with_a_reference_and_history(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/tickets', $this->validData());

        $ticket = Ticket::sole();
        $response->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas('success', "Ticket {$ticket->reference} submitted.");

        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertSame(Ticket::referenceFor($ticket->id), $ticket->reference);
        $this->assertMatchesRegularExpression('/^TKT-\d{6}$/', $ticket->reference);
        $this->assertNull($ticket->assigned_to);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'created',
            'from_status' => null,
            'to_status' => 'open',
        ]);
    }

    public function test_status_technician_and_owner_in_the_request_are_ignored(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $technician = User::factory()->technician()->create();

        $this->actingAs($user)->post('/tickets', $this->validData([
            'status' => 'resolved',
            'assigned_to' => $technician->id,
            'assigned_by' => $user->id,
            'user_id' => $otherUser->id,
            'reference' => 'TKT-999999',
        ]))->assertSessionHasNoErrors();

        $ticket = Ticket::sole();
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->assigned_by);
        $this->assertNotSame('TKT-999999', $ticket->reference);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/tickets/create')
            ->post('/tickets', [])
            ->assertRedirect('/tickets/create')
            ->assertSessionHasErrors(['title', 'description', 'category_id', 'priority']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $user = User::factory()->create();
        $inactive = Category::factory()->inactive()->create();

        $this->actingAs($user)->post('/tickets', $this->validData(['category_id' => $inactive->id]))
            ->assertSessionHasErrors(['category_id' => 'Choose one of the listed categories.']);
        $this->actingAs($user)->post('/tickets', $this->validData(['category_id' => 999999]))
            ->assertSessionHasErrors('category_id');
        $this->actingAs($user)->post('/tickets', $this->validData(['priority' => 'critical']))
            ->assertSessionHasErrors('priority');
        $this->actingAs($user)->post('/tickets', $this->validData(['title' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('title');
        $this->actingAs($user)->post('/tickets', $this->validData(['description' => str_repeat('a', 5001)]))
            ->assertSessionHasErrors('description');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_references_are_unique_per_ticket(): void
    {
        $tickets = Ticket::factory()->count(3)->create();

        $this->assertSame(3, $tickets->pluck('reference')->unique()->count());
        $this->assertSame(Ticket::referenceFor($tickets[0]->id), $tickets[0]->fresh()->reference);
    }
}
