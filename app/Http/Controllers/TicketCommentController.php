<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketCommentRequest;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;

class TicketCommentController extends Controller
{
    public function store(StoreTicketCommentRequest $request, Ticket $ticket, TicketService $tickets): RedirectResponse
    {
        $tickets->addComment($ticket, $request->user(), $request->validated('body'));

        return redirect()->to($ticket->urlFor($request->user()).'#comments')->with('success', __('Comment posted.'));
    }
}
