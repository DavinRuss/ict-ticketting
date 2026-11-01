<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $tickets->submitReview($ticket, $request->user(), $request->integer('rating'), $request->validated('comment'));

        return redirect()->route('tickets.show', $ticket)->with('success', __('Thank you for your feedback.'));
    }

    public function update(UpdateReviewRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $tickets->updateReview($ticket->review, $request->user(), $request->integer('rating'), $request->validated('comment'));

        return redirect()->route('tickets.show', $ticket)->with('success', __('Your review has been updated.'));
    }
}
