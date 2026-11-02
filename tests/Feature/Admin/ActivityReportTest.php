<?php

namespace Tests\Feature\Admin;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /** @param array<string, mixed> $attributes */
    private function activity(Ticket $ticket, string $action, ?User $actor, array $attributes = []): TicketActivity
    {
        $activity = $ticket->activities()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'from_status' => $attributes['from_status'] ?? null,
            'to_status' => $attributes['to_status'] ?? null,
            'from_technician_id' => $attributes['from_technician_id'] ?? null,
            'to_technician_id' => $attributes['to_technician_id'] ?? null,
            'note' => $attributes['note'] ?? null,
        ]);

        if (isset($attributes['created_at'])) {
            $activity->forceFill(['created_at' => $attributes['created_at']])->save();
        }

        return $activity;
    }

    public function test_only_admins_can_view_or_export_activity_reports(): void
    {
        $this->get('/admin/reports')->assertRedirect('/login');
        $this->get('/admin/reports/export')->assertRedirect('/login');

        foreach ([User::factory()->create(), User::factory()->technician()->create()] as $actor) {
            $this->actingAs($actor)->get('/admin/reports')->assertForbidden();
            $this->actingAs($actor)->get('/admin/reports/export')->assertForbidden();
            $this->actingAs($actor)->post('/admin/reports/export-selected', [])->assertForbidden();
        }

        $this->actingAs($this->admin)->get('/admin/reports')->assertOk()->assertSee('Activity reports');
    }

    public function test_admin_can_filter_activity_by_date_action_ticket_and_technician(): void
    {
        $technician = User::factory()->technician()->create(['name' => 'Tina Tech']);
        $otherTechnician = User::factory()->technician()->create(['name' => 'Tom Tech']);
        $requester = User::factory()->create();
        $matchingTicket = Ticket::factory()->for($requester, 'requester')->assignedTo($technician, $this->admin)->create();
        $otherTicket = Ticket::factory()->for($requester, 'requester')->assignedTo($otherTechnician, $this->admin)->create();
        $matching = $this->activity($matchingTicket, TicketActivity::ACTION_WORK_NOTE, $technician, [
            'note' => 'Replaced the network cable.',
            'created_at' => now()->subDays(2),
        ]);
        $this->activity($otherTicket, TicketActivity::ACTION_WORK_NOTE, $otherTechnician, [
            'note' => 'Other technician note.',
            'created_at' => now()->subDay(),
        ]);
        $this->activity($matchingTicket, TicketActivity::ACTION_ASSIGNED, $this->admin, [
            'to_technician_id' => $technician->id,
            'created_at' => now()->subDays(6),
        ]);

        $this->actingAs($this->admin)->get('/admin/reports?'.http_build_query([
            'from' => now()->subDays(3)->toDateString(),
            'to' => now()->subDay()->toDateString(),
            'ticket_reference' => substr($matchingTicket->reference, -4),
            'action' => TicketActivity::ACTION_WORK_NOTE,
            'technician_id' => $technician->id,
        ]))
            ->assertOk()
            ->assertSee('Replaced the network cable.')
            ->assertDontSee('Other technician note.')
            ->assertViewHas('activities', fn ($activities) => $activities->total() === 1
                && $activities->first()->id === $matching->id);
    }

    public function test_date_range_must_be_valid_and_ordered(): void
    {
        $this->actingAs($this->admin)->get('/admin/reports?from=not-a-date')->assertSessionHasErrors('from');
        $this->actingAs($this->admin)->get('/admin/reports?from=2026-11-02&to=2026-11-01')->assertSessionHasErrors(['from', 'to']);
        $this->actingAs($this->admin)->get('/admin/reports?action=not-an-action')->assertSessionHasErrors('action');
    }

    public function test_report_paginates_twenty_five_activities_per_page(): void
    {
        $ticket = Ticket::factory()->create();

        foreach (range(1, 26) as $number) {
            $this->activity($ticket, TicketActivity::ACTION_CREATED, $ticket->requester, ['note' => "Activity {$number}"]);
        }

        $this->actingAs($this->admin)->get('/admin/reports')
            ->assertViewHas('activities', fn ($activities) => $activities->count() === 25 && $activities->total() === 26);
        $this->actingAs($this->admin)->get('/admin/reports?page=2')
            ->assertViewHas('activities', fn ($activities) => $activities->count() === 1 && $activities->total() === 26);
    }

    public function test_csv_exports_filtered_selection_and_all_activity_safely(): void
    {
        $ticket = Ticket::factory()->create(['title' => 'Printer report']);
        $formula = $this->activity($ticket, TicketActivity::ACTION_WORK_NOTE, $ticket->requester, [
            'note' => '=HYPERLINK("https://example.invalid","click")',
        ]);
        $this->activity($ticket, TicketActivity::ACTION_CREATED, $ticket->requester, ['note' => 'Created request']);

        $filtered = $this->actingAs($this->admin)->get('/admin/reports/export?action='.TicketActivity::ACTION_WORK_NOTE);
        $filtered->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $filteredCsv = $filtered->streamedContent();
        $this->assertStringContainsString('Ticket reference', $filteredCsv);
        $this->assertStringContainsString($ticket->reference, $filteredCsv);
        $this->assertStringContainsString("'=HYPERLINK", $filteredCsv);
        $this->assertStringNotContainsString('Created request', $filteredCsv);

        $selected = $this->actingAs($this->admin)->post('/admin/reports/export-selected', [
            'activity_ids' => [$formula->id],
        ]);
        $selected->assertOk();
        $selectedCsv = $selected->streamedContent();
        $this->assertStringContainsString((string) $formula->id, $selectedCsv);
        $this->assertStringNotContainsString('Created request', $selectedCsv);

        $all = $this->actingAs($this->admin)->get('/admin/reports/export');
        $all->assertOk();
        $allCsv = $all->streamedContent();
        $this->assertStringContainsString('Created request', $allCsv);
        $this->assertStringContainsString((string) $formula->id, $allCsv);
    }

    public function test_selected_csv_requires_one_to_twenty_five_distinct_existing_activity_ids(): void
    {
        $this->actingAs($this->admin)->post('/admin/reports/export-selected', [])->assertSessionHasErrors('activity_ids');
        $this->actingAs($this->admin)->post('/admin/reports/export-selected', ['activity_ids' => [999999]])
            ->assertSessionHasErrors('activity_ids.0');
        $this->actingAs($this->admin)->post('/admin/reports/export-selected', ['activity_ids' => [1, 1]])
            ->assertSessionHasErrors('activity_ids.1');
        $this->actingAs($this->admin)->post('/admin/reports/export-selected', ['activity_ids' => range(1, 26)])
            ->assertSessionHasErrors('activity_ids');
    }
}
