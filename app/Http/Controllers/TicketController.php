<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Ticket::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
        ]);

        $tickets = $request->user()->tickets()
            ->with(['category', 'technician'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'statuses' => TicketStatus::cases(),
        ]);
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

        $ticket->load(['category', 'technician', 'activities' => fn ($query) => $query->with(['user', 'fromTechnician', 'toTechnician'])->oldest()->oldest('id')]);

        return view('tickets.show', ['ticket' => $ticket]);
    }
}
