<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\Obligation;
use App\Services\CompletenessReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletenessReportTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lab = $this->makeLab();
        $this->actingInLab($this->lab, 'compliance_specialist');

        Obligation::where('code', 'C01')->update([
            'last_completed' => '2025-01-01',
            'document_link' => 'https://drive.google.com/file/clia',
        ]);
        Obligation::where('code', 'C06')->update(['last_completed' => '2025-08-01']);
    }

    public function test_summary_counts_completed_and_missing(): void
    {
        $summary = app(CompletenessReportService::class)->summary();

        $this->assertSame(17, $summary['total']);
        $this->assertSame(2, $summary['completed']);
        $this->assertSame(15, $summary['missing']);
        $this->assertSame(1, $summary['with_links']);
    }

    public function test_rows_flag_completion_and_signers(): void
    {
        $rows = app(CompletenessReportService::class)->rows()->keyBy('code');

        $this->assertTrue($rows['C01']['completed']);
        $this->assertFalse($rows['C03']['completed']);
        $this->assertSame('Lab Director; Tech Supervisor', $rows['C02']['required_signers']);
    }

    public function test_csv_export_downloads(): void
    {
        $res = $this->get(route('reports.completeness.csv', $this->lab));

        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('C01', $res->streamedContent());
    }

    public function test_pdf_export_downloads(): void
    {
        $res = $this->get(route('reports.completeness.pdf', $this->lab));

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
    }
}
