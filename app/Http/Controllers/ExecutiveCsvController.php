<?php

namespace App\Http\Controllers;

use App\Services\ComplianceStatusService;
use App\Support\CurrentLab;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExecutiveCsvController extends Controller
{
    public function __invoke(Request $request, ComplianceStatusService $status, CurrentLab $current): StreamedResponse
    {
        $user = $request->user();
        $labs = $user->accessibleLabs();
        abort_unless($labs->count() >= 2 || $user->isSuperAdmin(), 403);

        $report = ExecutiveReportController::build($labs, $status, $current);
        $filename = 'overdue-across-labs-' . now()->toDateString() . '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Lab', 'ID', 'Obligation', 'Owner', 'Days Overdue', 'Last Completed']);
            foreach ($report as $row) {
                foreach ($row['overdue'] as $item) {
                    $o = $item['o'];
                    fputcsv($out, [
                        $row['lab']->name, $o->code, $o->name, $o->owner_role,
                        abs($item['days']), optional($o->last_completed)->toDateString() ?? '',
                    ]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
