<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewIndexRequest;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(ReviewIndexRequest $request): View
    {
        $filters = $request->validated();

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
