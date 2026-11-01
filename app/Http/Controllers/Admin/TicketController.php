<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignTicketRequest;
use App\Http\Requests\Admin\TicketIndexRequest as AdminTicketIndexRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TicketController extends Controller
{
    public const UNASSIGNED = AdminTicketIndexRequest::UNASSIGNED;

    public function index(AdminTicketIndexRequest $request): View
    {
        $filters = $request->validated();

        $tickets = Ticket::query()
            ->with(['requester', 'category', 'technician'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['category'] ?? null, fn (Builder $query, $category) => $query->where('category_id', $category))
            ->when($filters['technician'] ?? null, fn (Builder $query, $technician) => $technician === self::UNASSIGNED
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $technician))
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'statuses' => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
            'categories' => Category::orderBy('name')->get(),
            'technicians' => AdminTicketIndexRequest::filterableTechnicians(User::query())->orderBy('name')->get(),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        Gate::authorize('manage', Ticket::class);

        $ticket->load([
            'requester', 'category', 'technician', 'assigner', 'review',
        ]);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'comments' => $ticket->comments()->with('user')->paginate(15, ['*'], 'comments_page')->withQueryString(),
            'activities' => $ticket->activities()->with(['user', 'fromTechnician', 'toTechnician'])
                ->oldest()->oldest('id')->paginate(15, ['*'], 'activities_page')->withQueryString(),
            'technicians' => User::where('role', UserRole::Technician)->where('is_active', true)->orderBy('name')->get(),
            'canAssign' => $ticket->status->canTransitionTo(TicketStatus::Assigned),
            'nextStatuses' => $ticket->status->transitionsFor(UserRole::Admin),
        ]);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $wasAssigned = $ticket->assigned_to !== null;
        $ticket = $tickets->assign($ticket, User::findOrFail($request->integer('technician_id')), $request->user(), $request->validated('note'));

        return redirect()->route('admin.tickets.show', $ticket)->with('success', __($wasAssigned ? ':reference reassigned to :name.' : ':reference assigned to :name.', [
            'reference' => $ticket->reference,
            'name' => $ticket->technician->name,
        ]));
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $ticket = $tickets->changeStatus($ticket, $request->enum('status', TicketStatus::class), $request->user(), $request->validated('note'));

        return redirect()->route('admin.tickets.show', $ticket)->with('success', __(':reference is now :status.', [
            'reference' => $ticket->reference,
            'status' => strtolower($ticket->status->label()),
        ]));
    }
}
