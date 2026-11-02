<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Requests\DashboardRequest;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request): View
    {
        $actor = $request->user();
        $filters = $request->validated();
        $isAdmin = $actor->isAdmin();
        $isTechnician = $actor->hasRole(UserRole::Technician);
        $ticketQuery = Ticket::query();
        $selectedTechnician = null;

        if ($isAdmin && ($filters['technician_id'] ?? null)) {
            $selectedTechnician = User::findOrFail($filters['technician_id']);
            $ticketQuery->where('assigned_to', $selectedTechnician->id);
        } elseif ($isTechnician) {
            $ticketQuery->where('assigned_to', $actor->id);
        } elseif (! $isAdmin) {
            $ticketQuery->where('user_id', $actor->id);
        }

        $counts = (clone $ticketQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusCounts = collect(TicketStatus::cases())
            ->mapWithKeys(fn (TicketStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)]);
        $periodStart = now()->startOfDay()->subDays(13);
        $openedByDate = (clone $ticketQuery)
            ->where('created_at', '>=', $periodStart)
            ->selectRaw('DATE(created_at) as activity_date, count(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'activity_date');
        $progressQuery = TicketActivity::query()
            ->whereIn('ticket_id', (clone $ticketQuery)->select('tickets.id'))
            ->where('action', TicketActivity::ACTION_STATUS_CHANGED)
            ->whereIn('to_status', [TicketStatus::InProgress->value, TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->where('created_at', '>=', $periodStart);

        if ($selectedTechnician) {
            $progressQuery->where('user_id', $selectedTechnician->id);
        } elseif ($isTechnician) {
            $progressQuery->where('user_id', $actor->id);
        }

        $progressByDate = $progressQuery
            ->selectRaw('DATE(created_at) as activity_date, to_status, count(*) as total')
            ->groupByRaw('DATE(created_at), to_status')
            ->get();

        $progressMap = $progressByDate->groupBy('activity_date');
        $dailyActivity = collect(range(13, 0))->map(function (int $daysAgo) use ($openedByDate, $progressMap) {
            $date = now()->startOfDay()->subDays($daysAgo);
            $key = $date->toDateString();
            $events = $progressMap->get($key, collect())->keyBy('to_status');

            return [
                'date' => $key,
                'label' => $date->format('M j'),
                'opened' => (int) $openedByDate->get($key, 0),
                'started' => (int) ($events->get(TicketStatus::InProgress->value)->total ?? 0),
                'resolved' => (int) ($events->get(TicketStatus::Resolved->value)->total ?? 0),
                'closed' => (int) ($events->get(TicketStatus::Closed->value)->total ?? 0),
            ];
        })->values();

        $chartMaximum = max(1, (int) $dailyActivity->flatMap(fn (array $day) => [
            $day['opened'], $day['started'], $day['resolved'], $day['closed'],
        ])->max());
        $activitySeries = collect([
            ['key' => 'opened', 'label' => __('Opened'), 'color' => '#2563eb'],
            ['key' => 'started', 'label' => __('Started'), 'color' => '#d97706'],
            ['key' => 'resolved', 'label' => __('Resolved'), 'color' => '#15803d'],
            ['key' => 'closed', 'label' => __('Closed'), 'color' => '#64748b'],
        ])->map(function (array $series) use ($dailyActivity, $chartMaximum) {
            $coordinates = $dailyActivity->map(function (array $day, int $index) use ($series, $chartMaximum) {
                $x = 52 + (680 / 13 * $index);
                $y = 208 - (170 * $day[$series['key']] / $chartMaximum);

                return ['x' => round($x, 1), 'y' => round($y, 1), 'value' => $day[$series['key']]];
            });

            return [
                ...$series,
                'points' => $coordinates->map(fn (array $point) => $point['x'].','.$point['y'])->implode(' '),
                'coordinates' => $coordinates,
            ];
        })->values();

        $serviceMix = (clone $ticketQuery)
            ->join('categories', 'tickets.category_id', '=', 'categories.id')
            ->selectRaw('categories.name, count(tickets.id) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $technicians = collect();
        $recentActivity = collect();

        if ($isAdmin) {
            $technicians = User::query()
                ->where('role', UserRole::Technician->value)
                ->where('is_active', true)
                ->withCount([
                    'assignedTickets as active_tickets_count' => fn (Builder $query) => $query->whereIn('status', [TicketStatus::Assigned, TicketStatus::InProgress]),
                    'assignedTickets as in_progress_count' => fn (Builder $query) => $query->where('status', TicketStatus::InProgress),
                ])
                ->orderBy('name')
                ->get();
            $technicianOptions = $technicians;

            $technicianProgress = TicketActivity::query()
                ->whereIn('user_id', $technicians->modelKeys())
                ->whereIn('ticket_id', (clone $ticketQuery)->select('tickets.id'))
                ->where('action', TicketActivity::ACTION_STATUS_CHANGED)
                ->whereIn('to_status', [TicketStatus::InProgress->value, TicketStatus::Resolved->value])
                ->where('created_at', '>=', $periodStart)
                ->selectRaw('user_id, sum(case when to_status = ? then 1 else 0 end) as started_count, sum(case when to_status = ? then 1 else 0 end) as resolved_count', [TicketStatus::InProgress->value, TicketStatus::Resolved->value])
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            $technicians = $technicians->map(function (User $technician) use ($technicianProgress) {
                $progress = $technicianProgress->get($technician->id);
                $technician->setAttribute('started_count', (int) ($progress->started_count ?? 0));
                $technician->setAttribute('resolved_count', (int) ($progress->resolved_count ?? 0));

                return $technician;
            })->sortByDesc('resolved_count')->values();

            if ($selectedTechnician) {
                $technicians = $technicians->where('id', $selectedTechnician->id)->values();
            }

            $recentActivity = TicketActivity::query()
                ->with(['ticket', 'user', 'fromTechnician', 'toTechnician'])
                ->whereIn('ticket_id', (clone $ticketQuery)->select('tickets.id'))
                ->whereIn('action', [
                    TicketActivity::ACTION_CREATED,
                    TicketActivity::ACTION_ASSIGNED,
                    TicketActivity::ACTION_REASSIGNED,
                    TicketActivity::ACTION_STATUS_CHANGED,
                ])
                ->latest('created_at')
                ->limit(8)
                ->get();
        }

        $recentTickets = (clone $ticketQuery)
            ->with(['category', 'technician'])
            ->latest('created_at')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'isAdmin' => $isAdmin,
            'isTechnician' => $isTechnician,
            'selectedTechnician' => $selectedTechnician,
            'statusCounts' => $statusCounts,
            'statuses' => TicketStatus::cases(),
            'pendingCount' => $statusCounts[TicketStatus::Open->value] + $statusCounts[TicketStatus::Assigned->value],
            'inProgressCount' => $statusCounts[TicketStatus::InProgress->value],
            'finishedCount' => $statusCounts[TicketStatus::Resolved->value] + $statusCounts[TicketStatus::Closed->value],
            'dailyActivity' => $dailyActivity,
            'activitySeries' => $activitySeries,
            'chartMaximum' => $chartMaximum,
            'serviceMix' => $serviceMix,
            'serviceMixMaximum' => max(1, (int) ($serviceMix->max('total') ?? 0)),
            'technicians' => $technicians,
            'technicianOptions' => $technicianOptions ?? collect(),
            'recentActivity' => $recentActivity,
            'recentTickets' => $recentTickets,
        ]);
    }
}
