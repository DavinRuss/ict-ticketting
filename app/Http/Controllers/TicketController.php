<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Requests\Admin\AssignTicketRequest;
use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\TicketIndexRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(TicketIndexRequest $request): View
    {
        $filters = $request->validated();
        $isAdmin = $request->user()->isAdmin();

        $tickets = ($isAdmin ? Ticket::query() : $request->user()->tickets())
            ->with(['requester', 'category', 'technician'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'statuses' => TicketStatus::cases(),
            'isAdmin' => $isAdmin,
            'technicians' => $isAdmin
                ? User::query()->where('role', UserRole::Technician)->where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $wasAssigned = $ticket->assigned_to !== null;
        $ticket = $tickets->assign(
            $ticket,
            User::findOrFail($request->integer('technician_id')),
            $request->user(),
            $request->validated('note'),
        );

        return redirect()->route('tickets.index')->with('success', __($wasAssigned ? ':reference reassigned to :name.' : ':reference assigned to :name.', [
            'reference' => $ticket->reference,
            'name' => $ticket->technician->name,
        ]));
    }

    public function create(): View
    {
        Gate::authorize('create', Ticket::class);

        return view('tickets.create', [
            'categories' => Category::active()->orderBy('name')->get(),
            'priorities' => TicketPriority::cases(),
        ]);
    }

    public function store(StoreTicketRequest $request, TicketService $tickets): RedirectResponse
    {
        $ticket = $tickets->create($request->user(), $request->safe()->only(['title', 'description', 'category_id', 'priority']));

        return redirect()->route('tickets.show', $ticket)
            ->with('success', __('Ticket :reference submitted.', ['reference' => $ticket->reference]));
    }

    public function show(Ticket $ticket): View
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['category', 'technician', 'review']);

        return view('tickets.show', [
            'ticket' => $ticket,
            'comments' => $ticket->comments()->with('user')->paginate(15, ['*'], 'comments_page')->withQueryString(),
            'activities' => $ticket->activities()->visibleToRequester()->with(['user', 'fromTechnician', 'toTechnician'])
                ->oldest()->oldest('id')->paginate(15, ['*'], 'activities_page')->withQueryString(),
        ]);
    }

    public function storeComment(StoreTicketCommentRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $tickets->addComment($ticket, $request->user(), $request->validated('body'));

        return redirect()->route('tickets.show', $ticket)->with('success', __('Comment added.'));
    }

    /**
     * The requester confirms the fix by closing their resolved ticket.
     */
    public function close(Request $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        Gate::authorize('close', $ticket);

        $ticket = $tickets->changeStatus($ticket, TicketStatus::Closed, $request->user());

        return redirect()->route('tickets.show', $ticket)
            ->with('success', __(':reference is now closed. Thank you for confirming.', ['reference' => $ticket->reference]));
    }
}
