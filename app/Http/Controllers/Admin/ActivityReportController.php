<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityReportRequest;
use App\Http\Requests\Admin\ActivitySelectionRequest;
use App\Models\TicketActivity;
use App\Services\ActivityReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityReportController extends Controller
{
    public function index(ActivityReportRequest $request, ActivityReportService $report): View
    {
        $filters = $request->validated();
        $query = $report->query($filters);

        return view('admin.reports.index', [
            'activities' => (clone $query)->latest('created_at')->latest('id')->paginate(25)->withQueryString(),
            'total' => (clone $query)->count(),
            'filters' => $filters,
            'technicians' => $report->technicians(),
            'actions' => [
                TicketActivity::ACTION_CREATED => __('Ticket submitted'),
                TicketActivity::ACTION_ASSIGNED => __('Assigned'),
                TicketActivity::ACTION_REASSIGNED => __('Reassigned'),
                TicketActivity::ACTION_STATUS_CHANGED => __('Status changed'),
                TicketActivity::ACTION_WORK_NOTE => __('Work note'),
            ],
        ]);
    }

    public function export(ActivityReportRequest $request, ActivityReportService $report): StreamedResponse
    {
        return $report->download($request->validated());
    }

    public function exportSelected(ActivitySelectionRequest $request, ActivityReportService $report): StreamedResponse
    {
        return $report->downloadSelected(array_map('intval', $request->validated('activity_ids')));
    }
}
