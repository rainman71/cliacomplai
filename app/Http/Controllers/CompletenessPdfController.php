<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Services\CompletenessReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class CompletenessPdfController extends Controller
{
    public function __construct(private CompletenessReportService $report) {}

    public function __invoke(Lab $lab): Response
    {
        $pdf = Pdf::loadView('reports.completeness-pdf', [
            'rows' => $this->report->rows(),
            'summary' => $this->report->summary(),
            'generatedAt' => now()->toDayDateTimeString(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('completeness-report-' . now()->toDateString() . '.pdf');
    }
}
