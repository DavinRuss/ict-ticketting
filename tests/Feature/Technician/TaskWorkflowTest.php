<?php

namespace Tests\Feature\Technician;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $this->technician = User::factory()->technician()->create(['name' => 'Tina Tech']);
    }

    private function assigned(TicketStatus $status = TicketStatus::Assigned, ?User $technician = null, array $attributes = []): Ticket
    {
        return Ticket::factory()->assignedTo($technician ?? $this->technician, $this->admin)->create(['status' => $status, ...$attributes]);
    }

    // Access and visibility

    public function test_guests_are_redirected_to_login(): void
    {
        $ticket = $this->assigned();

        $this->get('/technician')->assertRedirect('/login');
        $this->get('/technician/tickets')->assertRedirect('/login');
        $this->get("/technician/tickets/{$ticket->id}")->assertRedirect('/login');
        $this->put("/technician/tickets/{$ticket->id}/status")->assertRedirect('/login');
        $this->post("/technician/tickets/{$ticket->id}/notes")->assertRedirect('/login');
    }

    public function test_users_and_admins_cannot_use_the_technician_workspace(): void
    {
        $ticket = $this->assigned();

        foreach ([User::factory()->create(), $this->admin] as $actor) {
            $this->actingAs($actor)->get('/technician/tickets')->assertForbidden();
            $this->actingAs($actor)->get("/technician/tickets/{$ticket->id}")->assertForbidden();
            $this->actingAs($actor)->put("/technician/tickets/{$ticket->id}/status", ['status' => 'in_progress'])->assertForbidden();
            $this->actingAs($actor)->post("/technician/tickets/{$ticket->id}/notes", ['note' => 'x'])->assertForbidden();
        }

        $this->assertSame(TicketStatus::Assigned, $ticket->fresh()->status);
        $this->assertSame(0, $ticket->activities()->count());
    }

    public function test_technician_sees_only_tickets_assigned_to_them(): void
    {
        $mine = $this->assigned(attributes: ['title' => 'My printer job']);
        $theirs = $this->assigned(technician: User::factory()->technician()->create(), attributes: ['title' => 'Other tech job']);
        $unassigned = Ticket::factory()->create(['title' => 'Nobody has this']);

        $this->actingAs($this->technician)->get('/technician/tickets')
            ->assertOk()
            ->assertSee('My printer job')
            ->assertDontSee('Other tech job')
            ->assertDontSee('Nobody has this')
            ->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('id')->all() === [$mine->id]);

        $this->actingAs($this->technician)->get("/technician/tickets/{$mine->id}")->assertOk()->assertSee('My printer job');
        $this->actingAs($this->technician)->get("/technician/tickets/{$theirs->id}")->assertForbidden();
        $this->actingAs($this->technician)->get("/technician/tickets/{$unassigned->id}")->assertForbidden();
        $this->actingAs($this->technician)->get("/tickets/{$mine->id}")->assertForbidden();
        $this->actingAs($this->technician)->get("/admin/tickets/{$mine->id}")->assertForbidden();
    }

    public function test_technician_loses_access_once_the_ticket_is_reassigned(): void
    {
        $ticket = $this->assigned();
        app(TicketService::class)->assign($ticket, User::factory()->technician()->create(), $this->admin);

        $this->actingAs($this->technician)->get("/technician/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($this->technician)->get('/technician/tickets')->assertViewHas('tickets', fn ($tickets) => $tickets->isEmpty());
    }

    public function test_dashboard_counts_only_the_technicians_own_assignments(): void
    {
        $this->assigned();
        $this->assigned(TicketStatus::InProgress, attributes: ['title' => 'Active job']);
        $this->assigned(TicketStatus::Resolved, attributes: ['title' => 'Finished job']);
        $this->assigned(technician: User::factory()->technician()->create());

        $this->actingAs($this->technician)->get('/technician')
            ->assertOk()
            ->assertSee('Technician workspace')
            ->assertSee('Active job')
            ->assertDontSee('Finished job')
            ->assertViewHas('statusCounts', fn ($counts) => $counts->all() === ['assigned' => 1, 'in_progress' => 1, 'resolved' => 1, 'closed' => 0]);
    }

    public function test_list_is_paginated_and_filterable(): void
    {
        foreach (range(1, 16) as $i) {
            $this->assigned(attributes: ['priority' => TicketPriority::Low]);
        }
        $inProgress = $this->assigned(TicketStatus::InProgress, attributes: ['priority' => TicketPriority::High]);
        $resolved = $this->assigned(TicketStatus::Resolved, attributes: ['priority' => TicketPriority::Low]);

        $this->actingAs($this->technician)->get('/technician/tickets')
            ->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 15 && $tickets->total() === 18);
        $this->actingAs($this->technician)->get('/technician/tickets?page=2')
            ->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 3);

        $this->actingAs($this->technician)->get('/technician/tickets?status=in_progress')
            ->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('id')->all() === [$inProgress->id]);
        $this->actingAs($this->technician)->get('/technician/tickets?status=active')
            ->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 17 && ! $tickets->contains('id', $resolved->id));
        $this->actingAs($this->technician)->get('/technician/tickets?priority=high')
            ->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('id')->all() === [$inProgress->id]);
        $this->actingAs($this->technician)->get('/technician/tickets?status=closed')
            ->assertOk()->assertSee('None of your assigned tickets match these filters.');

        $this->actingAs($this->technician)->get('/technician/tickets?status=bogus')->assertSessionHasErrors('status');
        $this->actingAs($this->technician)->get('/technician/tickets?priority=bogus')->assertSessionHasErrors('priority');
    }

    public function test_empty_state_when_nothing_is_assigned(): void
    {
        $this->actingAs($this->technician)->get('/technician/tickets')->assertOk()->assertSee('No tickets are assigned to you yet.');
    }

    // Permitted status changes

    public function test_technician_starts_and_resolves_their_ticket_with_history(): void
    {
        $ticket = $this->assigned();

        $this->actingAs($this->technician)->get("/technician/tickets/{$ticket->id}")
            ->assertViewHas('nextStatuses', [TicketStatus::InProgress]);

        $this->actingAs($this->technician)
            ->put("/technician/tickets/{$ticket->id}/status", ['status' => 'in_progress', 'note' => 'On my way to your desk.'])
            ->assertRedirect("/technician/tickets/{$ticket->id}")
            ->assertSessionHas('success', "{$ticket->reference} is now in progress.");

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertNotNull($ticket->started_at);

        $this->actingAs($this->technician)
            ->put("/technician/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);

        $history = $ticket->activities()->oldest('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame([TicketActivity::ACTION_STATUS_CHANGED, TicketActivity::ACTION_STATUS_CHANGED], $history->pluck('action')->all());
        $this->assertSame([$this->technician->id, $this->technician->id], $history->pluck('user_id')->all());
        $this->assertSame([TicketStatus::Assigned, TicketStatus::InProgress], $history->pluck('from_status')->all());
        $this->assertSame([TicketStatus::InProgress, TicketStatus::Resolved], $history->pluck('to_status')->all());
        $this->assertSame('On my way to your desk.', $history[0]->note);
        $this->assertNotNull($history[0]->created_at);

        $this->actingAs($this->technician)->get("/technician/tickets/{$ticket->id}")
            ->assertViewHas('nextStatuses', [])
            ->assertSee('The requester or an admin closes it once the fix is confirmed.');
    }

    // Denied updates

    public function test_technician_cannot_close_reassign_skip_or_reverse_statuses(): void
    {
        $cases = [
            [TicketStatus::Resolved, 'closed', 'A technician cannot move a ticket to Closed.'],
            [TicketStatus::Assigned, 'assigned', 'Only an admin can assign or reassign a technician, using the assignment form.'],
            [TicketStatus::Assigned, 'resolved', 'A ticket cannot move from Assigned to Resolved.'],
            [TicketStatus::InProgress, 'assigned', 'Only an admin can assign or reassign a technician, using the assignment form.'],
            [TicketStatus::Resolved, 'in_progress', 'A ticket cannot move from Resolved to In progress.'],
            [TicketStatus::Closed, 'resolved', 'A ticket cannot move from Closed to Resolved.'],
        ];

        foreach ($cases as [$from, $to, $message]) {
            $ticket = $this->assigned($from);

            $this->actingAs($this->technician)
                ->from("/technician/tickets/{$ticket->id}")
                ->put("/technician/tickets/{$ticket->id}/status", ['status' => $to])
                ->assertRedirect("/technician/tickets/{$ticket->id}")
                ->assertSessionHasErrorsIn('status', ['status' => $message]);

            $this->assertSame($from, $ticket->fresh()->status, "{$from->value} -> {$to}");
            $this->assertSame(0, $ticket->activities()->count());
        }

        $ticket = $this->assigned();
        $this->actingAs($this->technician)->put("/technician/tickets/{$ticket->id}/status", ['status' => 'bogus'])->assertSessionHasErrorsIn('status', 'status');
        $this->actingAs($this->technician)->put("/technician/tickets/{$ticket->id}/status", [])->assertSessionHasErrorsIn('status', 'status');
    }

    public function test_rejected_transition_is_shown_on_the_ticket_page(): void
    {
        $ticket = $this->assigned(TicketStatus::Resolved);

        $this->actingAs($this->technician)
            ->from("/technician/tickets/{$ticket->id}")
            ->followingRedirects()
            ->put("/technician/tickets/{$ticket->id}/status", ['status' => 'closed'])
            ->assertOk()
            ->assertSee('A technician cannot move a ticket to Closed.');
    }

    public function test_technician_cannot_update_tickets_assigned_to_someone_else(): void
    {
        $theirs = $this->assigned(technician: User::factory()->technician()->create());
        $unassigned = Ticket::factory()->create();

        foreach ([$theirs, $unassigned] as $ticket) {
            $before = $ticket->status;
            $this->actingAs($this->technician)->put("/technician/tickets/{$ticket->id}/status", ['status' => 'in_progress'])->assertForbidden();
            $this->actingAs($this->technician)->post("/technician/tickets/{$ticket->id}/notes", ['note' => 'Sneaky'])->assertForbidden();
            $this->actingAs($this->technician)->put("/admin/tickets/{$ticket->id}/status", ['status' => 'in_progress'])->assertForbidden();
            $this->assertSame($before, $ticket->fresh()->status);
            $this->assertSame(0, $ticket->activities()->count());
        }
    }

    public function test_technician_cannot_assign_or_change_ownership(): void
    {
        $ticket = $this->assigned();
        $other = User::factory()->technician()->create();
        $requester = $ticket->user_id;

        $this->actingAs($this->technician)->put("/admin/tickets/{$ticket->id}/assignment", ['technician_id' => $other->id])->assertForbidden();
        $this->actingAs($this->technician)->put("/technician/tickets/{$ticket->id}/status", [
            'status' => 'in_progress',
            'assigned_to' => $other->id,
            'assigned_by' => $this->technician->id,
            'user_id' => $this->technician->id,
            'title' => 'Changed',
        ])->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertSame($this->technician->id, $ticket->assigned_to);
        $this->assertSame($this->admin->id, $ticket->assigned_by);
        $this->assertSame($requester, $ticket->user_id);
        $this->assertNotSame('Changed', $ticket->title);
    }

    public function test_service_rejects_a_technician_acting_on_a_ticket_reassigned_away_from_them(): void
    {
        $ticket = $this->assigned();
        $stale = $ticket->replicate();
        $stale->id = $ticket->id;
        app(TicketService::class)->assign($ticket, User::factory()->technician()->create(), $this->admin);

        foreach ([
            fn () => app(TicketService::class)->changeStatus($stale, TicketStatus::InProgress, $this->technician),
            fn () => app(TicketService::class)->addWorkNote($stale, $this->technician, 'Too late'),
        ] as $action) {
            try {
                $action();
                $this->fail('Expected the stale technician to be rejected.');
            } catch (ValidationException $e) {
                $this->assertContains('This ticket is no longer assigned to you.', collect($e->errors())->flatten()->all());
            }
        }

        $this->assertSame(TicketStatus::Assigned, $ticket->fresh()->status);
        $this->assertSame(0, $ticket->activities()->where('user_id', $this->technician->id)->count());
    }

    // Work notes

    public function test_technician_adds_internal_work_notes_with_author_and_time(): void
    {
        $ticket = $this->assigned(TicketStatus::InProgress);

        $this->actingAs($this->technician)
            ->post("/technician/tickets/{$ticket->id}/notes", ['note' => 'Replaced the toner; testing.'])
            ->assertRedirect("/technician/tickets/{$ticket->id}")
            ->assertSessionHas('success', 'Work note added.');

        $note = $ticket->activities()->sole();
        $this->assertSame(TicketActivity::ACTION_WORK_NOTE, $note->action);
        $this->assertSame($this->technician->id, $note->user_id);
        $this->assertSame('Replaced the toner; testing.', $note->note);
        $this->assertNull($note->from_status);
        $this->assertNull($note->to_status);
        $this->assertNotNull($note->created_at);
        $this->assertSame(TicketStatus::InProgress, $ticket->fresh()->status);

        $this->actingAs($this->technician)->get("/technician/tickets/{$ticket->id}")
            ->assertSee('Replaced the toner; testing.')->assertSee('Internal')->assertSee('Tina Tech');
        $this->actingAs($this->admin)->get("/admin/tickets/{$ticket->id}")
            ->assertSee('Replaced the toner; testing.')->assertSee('Internal');
        $this->actingAs($ticket->requester)->get("/tickets/{$ticket->id}")
            ->assertOk()->assertDontSee('Replaced the toner; testing.');
    }

    public function test_work_notes_are_validated_and_closed_tickets_take_no_notes(): void
    {
        $ticket = $this->assigned();

        $this->actingAs($this->technician)->post("/technician/tickets/{$ticket->id}/notes", ['note' => ''])->assertSessionHasErrorsIn('note', 'note');
        $this->actingAs($this->technician)->post("/technician/tickets/{$ticket->id}/notes", ['note' => str_repeat('a', 2001)])->assertSessionHasErrorsIn('note', 'note');

        $closed = $this->assigned(TicketStatus::Closed);
        $this->actingAs($this->technician)->post("/technician/tickets/{$closed->id}/notes", ['note' => 'Late note'])
            ->assertSessionHasErrorsIn('note', ['note' => 'This ticket is closed. Work notes can no longer be added.']);

        $this->assertSame(0, TicketActivity::count());
    }
}
