<?php

namespace App\Services;

use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityReportService
{
    /**
     * @param  array{from?: string, to?: string, action?: string, ticket_reference?: string, technician_id?: int|string}  $filters
     * @return Builder<TicketActivity>
     */
    public function query(array $filters): Builder
    {
        return TicketActivity::query()
            ->with(['ticket.requester', 'user', 'fromTechnician', 'toTechnician'])
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['ticket_reference'] ?? null, fn (Builder $query, string $reference) => $query->whereHas(
                'ticket',
                fn (Builder $ticket) => $ticket->where('reference', 'like', '%'.$reference.'%'),
            ))
            ->when($filters['technician_id'] ?? null, fn (Builder $query, int|string $technicianId) => $query->where(
                fn (Builder $query) => $query
                    ->where('user_id', $technicianId)
                    ->orWhere('from_technician_id', $technicianId)
                    ->orWhere('to_technician_id', $technicianId),
            ));
    }

    /**
     * @return Collection<int, User>
     */
    public function technicians()
    {
        return User::query()
            ->where(function ($query) {
                $query->where('role', 'technician')
                    ->orWhereIn('id', TicketActivity::query()->select('from_technician_id')->whereNotNull('from_technician_id'))
                    ->orWhereIn('id', TicketActivity::query()->select('to_technician_id')->whereNotNull('to_technician_id'));
            })
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $filters */
    public function download(array $filters): StreamedResponse
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $filename = 'ticket-activity-'.($from ?: 'all').'-'.($to ?: 'dates').'.csv';

        return $this->stream($this->query($filters), $filename);
    }

    /** @param list<int> $activityIds */
    public function downloadSelected(array $activityIds): StreamedResponse
    {
        $query = TicketActivity::query()
            ->with(['ticket.requester', 'user', 'fromTechnician', 'toTechnician'])
            ->whereIn('id', $activityIds);

        return $this->stream($query, 'ticket-activity-selected.csv');
    }

    /** @param Builder<TicketActivity> $query */
    private function stream(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Activity ID',
                'Occurred at',
                'Ticket reference',
                'Ticket title',
                'Requester',
                'Action',
                'Actor',
                'Status from',
                'Status to',
                'Technician from',
                'Technician to',
                'Note',
            ], ',', '"', '');

            foreach ($query->lazyById(500) as $activity) {
                fputcsv($stream, array_map($this->csvCell(...), [
                    (string) $activity->id,
                    $activity->created_at?->toIso8601String() ?? '',
                    $activity->ticket->reference,
                    $activity->ticket->title,
                    $activity->ticket->requester->name,
                    $activity->action,
                    $activity->user?->name ?? 'System',
                    $activity->from_status?->label() ?? '',
                    $activity->to_status?->label() ?? '',
                    $activity->fromTechnician?->name ?? '',
                    $activity->toTechnician?->name ?? '',
                    $activity->note ?? '',
                ]), ',', '"', '');
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@-]/u', $value) === 1 ? "'".$value : $value;
    }
}
