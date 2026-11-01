<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Review::class);

        $filters = $request->validate([
            'rating' => ['nullable', 'integer', 'between:'.Review::MIN_RATING.','.Review::MAX_RATING],
            'technician' => ['nullable', 'integer', Rule::exists('tickets', 'assigned_to')],
        ]);

        $query = Review::query()
            ->when($filters['rating'] ?? null, fn (Builder $query, $rating) => $query->where('rating', $rating))
            ->when($filters['technician'] ?? null, fn (Builder $query, $technician) => $query->whereHas('ticket', fn (Builder $ticket) => $ticket->where('assigned_to', $technician)));

        return view('admin.reviews.index', [
            'reviews' => (clone $query)->with(['user', 'ticket.technician', 'ticket.category'])->latest()->latest('id')->paginate(15)->withQueryString(),
            'average' => (clone $query)->avg('rating'),
            'filters' => $filters,
            'ratings' => range(Review::MAX_RATING, Review::MIN_RATING),
            'technicians' => User::query()->whereIn('id', Ticket::query()->select('assigned_to')->whereNotNull('assigned_to'))->orderBy('name')->get(),
        ]);
    }
}
