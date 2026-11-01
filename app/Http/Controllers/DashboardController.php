<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $counts = $request->user()->tickets()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard', [
            'statusCounts' => collect(TicketStatus::cases())->mapWithKeys(fn (TicketStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)]),
            'statuses' => TicketStatus::cases(),
            'recentTickets' => $request->user()->tickets()->with('category')->latest()->latest('id')->limit(5)->get(),
        ]);
    }
}
