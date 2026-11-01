<?php

namespace Tests\Feature\Admin;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class TicketManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    }

    private function inStatus(TicketStatus $status, ?User $technician = null): Ticket
    {
        $technician ??= User::factory()->technician()->create();
        $factory = Ticket::factory();

        if ($status !== TicketStatus::Open) {
            $factory = $factory->assignedTo($technician, $this->admin);
        }

        return $factory->create(['status' => $status]);
    }

    // Access

    public function test_guests_are_redirected_to_login(): void
    {
        $ticket = Ticket::factory()->create();

        $this->get('/admin/tickets')->assertRedirect('/login');
        $this->get("/admin/tickets/{$ticket->id}")->assertRedirect('/login');
        $this->put("/admin/tickets/{$ticket->id}/assignment")->assertRedirect('/login');
        $this->put("/admin/tickets/{$ticket->id}/status")->assertRedirect('/login');
    }

    public function test_users_and_technicians_are_denied_every_admin_ticket_action(): void
    {
        $technician = User::factory()->technician()->create();
        $ticket = Ticket::factory()->assignedTo($technician, $this->admin)->create();

        foreach ([User::factory()->create(), $technician] as $actor) {
            $this->actingAs($actor)->get('/admin/tickets')->assertForbidden();
            $this->actingAs($actor)->get("/admin/tickets/{$ticket->id}")->assertForbidden();
            $this->actingAs($actor)->put("/admin/tickets/{$ticket->id}/assignment", ['technician_id' => $technician->id])->assertForbidden();
            $this->actingAs($actor)->put("/admin/tickets/{$ticket->id}/status", ['status' => 'in_progress'])->assertForbidden();
        }

        $this->assertSame(TicketStatus::Assigned, $ticket->fresh()->status);
        $this->assertSame(0, $ticket->activities()->count());
    }

    public function test_admin_sees_every_users_tickets_and_any_ticket_detail(): void
    {
        $first = Ticket::factory()->create(['title' => 'Printer on fire']);
        Ticket::factory()->create(['title' => 'VPN drops hourly']);

        $this->actingAs($this->admin)->get('/admin/tickets')
            ->assertOk()
            ->assertSee('Printer on fire')
            ->assertSee('VPN drops hourly');

        $this->actingAs($this->admin)->get("/admin/tickets/{$first->id}")
            ->assertOk()
            ->assertSee($first->reference)
            ->assertSee($first->requester->email)
            ->assertSee('Assign technician');
    }

    public function test_admin_navigation_links_to_all_tickets_only_for_admins(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertSee(route('admin.tickets.index'));
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee(route('admin.tickets.index'));
    }

    // List filters

    public function test_list_is_paginated_fifteen_per_page(): void
    {
        Ticket::factory()->count(16)->create();

        $this->actingAs($this->admin)->get('/admin/tickets')->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 15 && $tickets->total() === 16);
        $this->actingAs($this->admin)->get('/admin/tickets?page=2')->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 1);
    }

    public function test_list_filters_by_status_priority_category_and_technician(): void
    {
        $tina = User::factory()->technician()->create();
        $tom = User::factory()->technician()->create();
        $network = Category::factory()->create();

        $open = Ticket::factory()->create(['priority' => TicketPriority::Low]);
        $tinas = Ticket::factory()->assignedTo($tina, $this->admin)->create(['priority' => TicketPriority::Urgent, 'category_id' => $network->id]);
        $toms = Ticket::factory()->assignedTo($tom, $this->admin)->create(['priority' => TicketPriority::Low]);

        $ids = fn (string $query) => $this->actingAs($this->admin)->get("/admin/tickets?{$query}")->assertOk()->viewData('tickets')->pluck('id')->sort()->values()->all();

        $this->assertSame([$open->id], $ids('status=open'));
        $this->assertSame([$tinas->id], $ids('priority=urgent'));
        $this->assertSame([$tinas->id], $ids("category={$network->id}"));
        $this->assertSame([$toms->id], $ids("technician={$tom->id}"));
        $this->assertSame([$open->id], $ids('technician=unassigned'));
        $this->assertSame([$toms->id], $ids('status=assigned&priority=low'));
    }

    public function test_tickets_of_a_demoted_technician_stay_filterable(): void
    {
        $former = User::factory()->technician()->create(['name' => 'Former Tech']);
        $ticket = Ticket::factory()->assignedTo($former, $this->admin)->create();
        Ticket::factory()->create();
        $former->forceFill(['role' => 'user'])->save();

        $this->actingAs($this->admin)->get("/admin/tickets?technician={$former->id}")
            ->assertOk()
            ->assertSessionHasNoErrors()
            ->assertSee('Former Tech')
            ->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('id')->all() === [$ticket->id]);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->get('/admin/tickets?status=bogus')->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->get('/admin/tickets?priority=bogus')->assertSessionHasErrors('priority');
        $this->actingAs($this->admin)->get('/admin/tickets?category=999999')->assertSessionHasErrors('category');
        $this->actingAs($this->admin)->get("/admin/tickets?technician={$user->id}")->assertSessionHasErrors('technician');
    }

    public function test_empty_states(): void
    {
        $this->actingAs($this->admin)->get('/admin/tickets')->assertSee('No tickets have been submitted yet.');

        Ticket::factory()->create();
        $this->actingAs($this->admin)->get('/admin/tickets?status=closed')->assertSee('No tickets match these filters.');
    }

    // Assignment

    public function test_admin_assigns_an_open_ticket_and_history_is_recorded(): void
    {
        $technician = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->admin)
            ->put("/admin/tickets/{$ticket->id}/assignment", ['technician_id' => $technician->id, 'note' => 'Tina knows this printer.'])
            ->assertRedirect("/admin/tickets/{$ticket->id}")
            ->assertSessionHas('success', "{$ticket->reference} assigned to Tina Tech.");

        $ticket->refresh();
        $this->assertSame(TicketStatus::Assigned, $ticket->status);
        $this->assertSame($technician->id, $ticket->assigned_to);
        $this->assertSame($this->admin->id, $ticket->assigned_by);
        $this->assertNotNull($ticket->assigned_at);

        $activity = $ticket->activities()->sole();
        $this->assertSame(TicketActivity::ACTION_ASSIGNED, $activity->action);
        $this->assertSame($this->admin->id, $activity->user_id);
        $this->assertSame(TicketStatus::Open, $activity->from_status);
        $this->assertSame(TicketStatus::Assigned, $activity->to_status);
        $this->assertNull($activity->from_technician_id);
        $this->assertSame($technician->id, $activity->to_technician_id);
        $this->assertSame('Tina knows this printer.', $activity->note);
        $this->assertNotNull($activity->created_at);

        $this->actingAs($this->admin)->get("/admin/tickets/{$ticket->id}")->assertSee('Assigned to Tina Tech')->assertSee('Ada Admin');
        $this->actingAs($ticket->requester)->get("/tickets/{$ticket->id}")->assertSee('Assigned to Tina Tech');
    }

    public function test_reassignment_keeps_the_previous_assignment_in_history(): void
    {
        $tina = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $tom = User::factory()->technician()->create(['name' => 'Tom Tech']);
        $ticket = Ticket::factory()->create();
        $service = app(TicketService::class);
        $service->assign($ticket, $tina, $this->admin);
        $secondAdmin = User::factory()->admin()->create();

        $this->actingAs($secondAdmin)
            ->put("/admin/tickets/{$ticket->id}/assignment", ['technician_id' => $tom->id])
            ->assertSessionHas('success', "{$ticket->reference} reassigned to Tom Tech.");

        $ticket->refresh();
        $this->assertSame(TicketStatus::Assigned, $ticket->status);
        $this->assertSame($tom->id, $ticket->assigned_to);
        $this->assertSame($secondAdmin->id, $ticket->assigned_by);

        $history = $ticket->activities()->oldest('id')->get();
        $this->assertSame([TicketActivity::ACTION_ASSIGNED, TicketActivity::ACTION_REASSIGNED], $history->pluck('action')->all());
        $this->assertSame([$tina->id, $tom->id], [$history[1]->from_technician_id, $history[1]->to_technician_id]);
        $this->assertSame($secondAdmin->id, $history[1]->user_id);

        $this->actingAs($this->admin)->get("/admin/tickets/{$ticket->id}")->assertSee('Reassigned from Tina Tech to Tom Tech');
    }

    public function test_tickets_cannot_be_assigned_to_accounts_that_are_not_active_technicians(): void
    {
        $ticket = Ticket::factory()->create();

        foreach ([
            User::factory()->create()->id,
            User::factory()->admin()->create()->id,
            User::factory()->technician()->inactive()->create()->id,
            999999,
            'abc',
            null,
        ] as $technicianId) {
            $this->actingAs($this->admin)
                ->from("/admin/tickets/{$ticket->id}")
                ->put("/admin/tickets/{$ticket->id}/assignment", ['technician_id' => $technicianId])
                ->assertRedirect("/admin/tickets/{$ticket->id}")
                ->assertSessionHasErrorsIn('assignment', 'technician_id');
        }

        $ticket->refresh();
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->assigned_to);
        $this->assertSame(0, $ticket->activities()->count());
    }

    public function test_service_also_refuses_non_technicians(): void
    {
        $ticket = Ticket::factory()->create();

        $this->expectException(ValidationException::class);

        try {
            app(TicketService::class)->assign($ticket, User::factory()->create(), $this->admin);
        } finally {
            $this->assertNull($ticket->fresh()->assigned_to);
            $this->assertSame(0, $ticket->activities()->count());
        }
    }

    public function test_assigning_the_current_technician_again_is_rejected(): void
    {
        $technician = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $ticket = Ticket::factory()->assignedTo($technician, $this->admin)->create();

        $this->actingAs($this->admin)
            ->put("/admin/tickets/{$ticket->id}/assignment", ['technician_id' => $technician->id])
            ->assertSessionHasErrorsIn('assignment', ['technician_id' => 'This ticket is already assigned to Tina Tech.']);

        $this->assertSame(0, $ticket->activities()->count());
    }

    public function test_tickets_past_assigned_cannot_be_reassigned(): void
    {
        $other = User::factory()->technician()->create();

        foreach ([TicketStatus::InProgress, TicketStatus::Resolved, TicketStatus::Closed] as $status) {
            $ticket = $this->inStatus($status);
            $before = $ticket->assigned_to;

            $this->actingAs($this->admin)
                ->put("/admin/tickets/{$ticket->id}/assignment", ['technician_id' => $other->id])
                ->assertSessionHasErrorsIn('assignment', 'technician_id');

            $this->assertSame($before, $ticket->fresh()->assigned_to);
            $this->assertSame($status, $ticket->fresh()->status);
        }
    }

    // Status changes

    public function test_admin_moves_a_ticket_through_the_lifecycle_and_each_step_is_logged(): void
    {
        $ticket = $this->inStatus(TicketStatus::Assigned);

        $steps = [
            [TicketStatus::InProgress, 'started_at'],
            [TicketStatus::Resolved, 'resolved_at'],
            [TicketStatus::Closed, 'closed_at'],
        ];

        foreach ($steps as [$to, $timestamp]) {
            $from = $ticket->fresh()->status;

            $this->actingAs($this->admin)
                ->put("/admin/tickets/{$ticket->id}/status", ['status' => $to->value, 'note' => "Now {$to->value}"])
                ->assertRedirect("/admin/tickets/{$ticket->id}")
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success');

            $fresh = $ticket->fresh();
            $this->assertSame($to, $fresh->status);
            $this->assertNotNull($fresh->{$timestamp});

            $activity = $ticket->activities()->latest('id')->first();
            $this->assertSame(TicketActivity::ACTION_STATUS_CHANGED, $activity->action);
            $this->assertSame($this->admin->id, $activity->user_id);
            $this->assertSame($from, $activity->from_status);
            $this->assertSame($to, $activity->to_status);
            $this->assertSame("Now {$to->value}", $activity->note);
        }

        $this->assertSame(3, $ticket->activities()->count());
        $this->actingAs($this->admin)->get("/admin/tickets/{$ticket->id}")
            ->assertSee('Status changed from Assigned to In progress')
            ->assertSee('This ticket is closed. Its status can no longer change.');
    }

    public function test_invalid_transitions_are_rejected_with_a_clear_message(): void
    {
        $cases = [
            [TicketStatus::Open, TicketStatus::InProgress, 'A ticket cannot move from Open to In progress.'],
            [TicketStatus::Open, TicketStatus::Resolved, 'A ticket cannot move from Open to Resolved.'],
            [TicketStatus::Open, TicketStatus::Closed, 'A ticket cannot move from Open to Closed.'],
            [TicketStatus::Assigned, TicketStatus::Resolved, 'A ticket cannot move from Assigned to Resolved.'],
            [TicketStatus::InProgress, TicketStatus::Closed, 'A ticket cannot move from In progress to Closed.'],
            [TicketStatus::InProgress, TicketStatus::Open, 'A ticket cannot move from In progress to Open.'],
            [TicketStatus::Resolved, TicketStatus::InProgress, 'A ticket cannot move from Resolved to In progress.'],
            [TicketStatus::Closed, TicketStatus::Open, 'A ticket cannot move from Closed to Open.'],
            [TicketStatus::Closed, TicketStatus::Closed, 'A ticket cannot move from Closed to Closed.'],
            [TicketStatus::Open, TicketStatus::Assigned, 'Use the assignment form to assign or reassign a technician.'],
            [TicketStatus::Assigned, TicketStatus::Assigned, 'Use the assignment form to assign or reassign a technician.'],
        ];

        foreach ($cases as [$from, $to, $message]) {
            $ticket = $this->inStatus($from);
            $before = $ticket->fresh()->getAttributes();

            $this->actingAs($this->admin)
                ->from("/admin/tickets/{$ticket->id}")
                ->put("/admin/tickets/{$ticket->id}/status", ['status' => $to->value])
                ->assertRedirect("/admin/tickets/{$ticket->id}")
                ->assertSessionHasErrorsIn('status', ['status' => $message]);

            $this->assertSame($before, $ticket->fresh()->getAttributes(), "{$from->value} -> {$to->value} changed the ticket");
            $this->assertSame(0, $ticket->activities()->count());
        }
    }

    public function test_unknown_status_values_are_rejected(): void
    {
        $ticket = $this->inStatus(TicketStatus::Assigned);

        $this->actingAs($this->admin)->put("/admin/tickets/{$ticket->id}/status", ['status' => 'done'])->assertSessionHasErrorsIn('status', 'status');
        $this->actingAs($this->admin)->put("/admin/tickets/{$ticket->id}/status", [])->assertSessionHasErrorsIn('status', 'status');
        $this->assertSame(TicketStatus::Assigned, $ticket->fresh()->status);
    }

    public function test_rejected_transition_message_is_shown_on_the_ticket_page(): void
    {
        $ticket = $this->inStatus(TicketStatus::Resolved);

        $this->actingAs($this->admin)
            ->from("/admin/tickets/{$ticket->id}")
            ->followingRedirects()
            ->put("/admin/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertSee('A ticket cannot move from Resolved to In progress.');
    }

    public function test_a_failed_history_write_rolls_back_the_ticket_change(): void
    {
        $ticket = $this->inStatus(TicketStatus::Open);
        $technician = User::factory()->technician()->create();
        TicketActivity::creating(fn () => throw new RuntimeException('history write failed'));

        try {
            app(TicketService::class)->assign($ticket, $technician, $this->admin);
            $this->fail('Expected the history write to fail.');
        } catch (RuntimeException) {
            // expected
        } finally {
            TicketActivity::flushEventListeners();
        }

        $ticket->refresh();
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->assigned_at);
    }
}
