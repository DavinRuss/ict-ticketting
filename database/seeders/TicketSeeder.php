<?php

namespace Database\Seeders;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    /**
     * @var list<array{string, string, string}> [category, title, status]
     */
    private const SAMPLES = [
        ['Hardware', 'Laptop will not power on', 'open'],
        ['Software', 'Request Microsoft Office installation', 'open'],
        ['Network', 'Wi-Fi drops every few minutes in meeting room', 'open'],
        ['Email', 'Cannot send attachments larger than 5 MB', 'open'],
        ['Printer', 'Second-floor printer paper jam', 'assigned'],
        ['Account/Access', 'Locked out of HR portal', 'assigned'],
        ['Hardware', 'External monitor flickering', 'assigned'],
        ['Software', 'Accounting app crashes on startup', 'in_progress'],
        ['Network', 'VPN connection fails from home', 'in_progress'],
        ['Email', 'Shared mailbox not showing in Outlook', 'in_progress'],
        ['Printer', 'Scanner not sending to email', 'resolved'],
        ['Account/Access', 'New starter needs system access', 'resolved'],
        ['Hardware', 'Keyboard keys not responding', 'resolved'],
        ['Software', 'Antivirus update failing', 'closed'],
        ['Network', 'Slow internet in finance office', 'closed'],
    ];

    public function run(): void
    {
        $admin = User::where('role', UserRole::Admin)->firstOrFail();
        $technicians = User::where('role', UserRole::Technician)->orderBy('id')->get();
        $requesters = User::where('role', UserRole::User)->orderBy('id')->get();
        $categories = Category::pluck('id', 'name');

        foreach (self::SAMPLES as $i => [$category, $title, $status]) {
            $technician = $technicians[$i % $technicians->count()];

            $factory = Ticket::factory()->state([
                'user_id' => $requesters[$i % $requesters->count()]->id,
                'category_id' => $categories[$category],
                'title' => $title,
            ]);

            $factory = match (TicketStatus::from($status)) {
                TicketStatus::Open => $factory,
                TicketStatus::Assigned => $factory->assigned($technician, $admin),
                TicketStatus::InProgress => $factory->inProgress($technician, $admin),
                TicketStatus::Resolved => $factory->resolved($technician, $admin),
                TicketStatus::Closed => $factory->closed($technician, $admin),
            };

            $ticket = $factory->create();

            $this->recordHistory($ticket);
            $this->addSampleComments($ticket);

            if ($ticket->status === TicketStatus::Closed || $title === 'Scanner not sending to email') {
                Review::factory()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $ticket->user_id,
                    'created_at' => $ticket->resolved_at->copy()->addHours(2),
                    'updated_at' => $ticket->resolved_at->copy()->addHours(2),
                ]);
            }
        }
    }

    /**
     * Write the audit trail implied by the ticket's current status.
     */
    private function recordHistory(Ticket $ticket): void
    {
        $steps = [
            ['created', $ticket->user_id, null, TicketStatus::Open, $ticket->created_at, null],
        ];

        if ($ticket->assigned_at) {
            $steps[] = ['assigned', $ticket->assigned_by, TicketStatus::Open, TicketStatus::Assigned, $ticket->assigned_at, 'Assigned to '.$ticket->technician->name];
        }
        if ($ticket->started_at) {
            $steps[] = ['status_changed', $ticket->assigned_to, TicketStatus::Assigned, TicketStatus::InProgress, $ticket->started_at, null];
        }
        if ($ticket->resolved_at) {
            $steps[] = ['status_changed', $ticket->assigned_to, TicketStatus::InProgress, TicketStatus::Resolved, $ticket->resolved_at, 'Issue fixed and confirmed working.'];
        }
        if ($ticket->closed_at) {
            $steps[] = ['status_changed', $ticket->user_id, TicketStatus::Resolved, TicketStatus::Closed, $ticket->closed_at, null];
        }

        foreach ($steps as [$action, $userId, $from, $to, $at, $note]) {
            $ticket->activities()->forceCreate([
                'user_id' => $userId,
                'action' => $action,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'created_at' => $at,
            ]);
        }
    }

    private function addSampleComments(Ticket $ticket): void
    {
        if (! $ticket->started_at) {
            return;
        }

        $ticket->comments()->forceCreate([
            'user_id' => $ticket->assigned_to,
            'body' => 'I am looking into this now.',
            'created_at' => $ticket->started_at,
            'updated_at' => $ticket->started_at,
        ]);

        $ticket->comments()->forceCreate([
            'user_id' => $ticket->user_id,
            'body' => 'Thanks, I am at my desk if you need me.',
            'created_at' => $ticket->started_at->copy()->addMinutes(30),
            'updated_at' => $ticket->started_at->copy()->addMinutes(30),
        ]);
    }
}
