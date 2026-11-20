<?php

namespace App\Http\Controllers\Technician;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkNoteRequest;
use App\Http\Requests\Technician\TicketIndexRequest as TechnicianTicketIndexRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The technician's workspace. Every query starts from the technician's own
 * assignedTickets(), and single tickets go through TicketPolicy::work().
 */
class TicketController extends Controller
{
    /**
     * Filter value for tickets still needing work (assigned or in progress).
     */
    public const ACTIVE = TechnicianTicketIndexRequest::ACTIVE;

    private const ACTIVE_STATUSES = [TicketStatus::Assigned, TicketStatus::InProgress];

    public function dashboard(Request $request): View
    {
        $counts = $request->user()->assignedTickets()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = array_values(array_filter(TicketStatus::cases(), fn (TicketStatus $status) => $status !== TicketStatus::Open));

        return view('technician.dashboard', [
            'statuses' => $statuses,
            'statusCounts' => collect($statuses)->mapWithKeys(fn (TicketStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)]),
            'activeTickets' => $request->user()->assignedTickets()
                ->with('category')
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->latest('assigned_at')
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }

    public function index(TechnicianTicketIndexRequest $request): View
    {
        $filters = $request->validated();

        $tickets = $request->user()->assignedTickets()
            ->with(['requester', 'category'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $status === self::ACTIVE
                ? $query->whereIn('status', self::ACTIVE_STATUSES)
                : $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->latest('assigned_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('technician.tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        Gate::authorize('work', $ticket);

        $ticket->load([
            'requester', 'category', 'assigner',
        ]);

        return view('technician.tickets.show', [
            'ticket' => $ticket,
            'comments' => $ticket->comments()->with('user')->paginate(15, ['*'], 'comments_page')->withQueryString(),
            'activities' => $ticket->activities()->with(['user', 'fromTechnician', 'toTechnician'])
                ->oldest()->oldest('id')->paginate(15, ['*'], 'activities_page')->withQueryString(),
            'nextStatuses' => $ticket->status->transitionsFor(UserRole::Technician),
        ]);
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $ticket = $tickets->changeStatus($ticket, $request->enum('status', TicketStatus::class), $request->user(), $request->validated('note'));

        return redirect()->route('technician.tickets.show', $ticket)->with('success', __(':reference is now :status.', [
            'reference' => $ticket->reference,
            'status' => strtolower($ticket->status->label()),
        ]));
    }

    public function storeNote(StoreWorkNoteRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $tickets->addWorkNote($ticket, $request->user(), $request->validated('note'));

        return redirect()->route('technician.tickets.show', $ticket)->with('success', __('Work note added.'));
    }
}
