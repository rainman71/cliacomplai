<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Services\CompletenessReportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompletenessCsvController extends Controller
{
    public function __construct(private CompletenessReportService $report) {}

    public function __invoke(Lab $lab): StreamedResponse
    {
        $rows = $this->report->rows();
        $filename = 'completeness-report-' . now()->toDateString() . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Category', 'Obligation', 'Owner', 'Frequency', 'Completed',
                'Last Completed', 'Next Due', 'Days Until Due', 'Status',
                'Signature Status', 'Required Signers', 'Document Link',
            ]);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['code'], $r['category'], $r['name'], $r['owner_role'], $r['frequency'],
                    $r['completed'] ? 'Yes' : 'No',
                    $r['last_completed'] ?? '', $r['next_due'] ?? '', $r['days_until_due'] ?? '',
                    $r['status'], $r['signature_status'], $r['required_signers'],
                    $r['document_link'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
