<?php

namespace Tests\Feature;

use App\Enums\ComplianceStatus;
use App\Models\Obligation;
use App\Services\ComplianceStatusService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ComplianceStatusTest extends TestCase
{
    private ComplianceStatusService $svc;
    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ComplianceStatusService();
        $this->today = CarbonImmutable::parse('2026-06-20'); // fixed reference for determinism
    }

    public function test_next_due_is_last_completed_plus_interval(): void
    {
        $nextDue = $this->svc->nextDue(CarbonImmutable::parse('2026-01-15'), 6);

        $this->assertSame('2026-07-15', $nextDue->toDateString());
    }

    public function test_next_due_is_null_without_a_baseline(): void
    {
        $this->assertNull($this->svc->nextDue(null, 12));
    }

    public function test_next_due_is_null_when_interval_missing_like_c13(): void
    {
        $this->assertNull($this->svc->nextDue(CarbonImmutable::parse('2026-01-15'), null));
    }

    public function test_days_until_due_keeps_sign_for_overdue(): void
    {
        $this->assertSame(10, $this->svc->daysUntilDue(CarbonImmutable::parse('2026-06-30'), $this->today));
        $this->assertSame(-5, $this->svc->daysUntilDue(CarbonImmutable::parse('2026-06-15'), $this->today));
        $this->assertNull($this->svc->daysUntilDue(null, $this->today));
    }

    public function test_days_since_verified(): void
    {
        $this->assertSame(40, $this->svc->daysSinceVerified(CarbonImmutable::parse('2026-05-11'), $this->today));
        $this->assertNull($this->svc->daysSinceVerified(null, $this->today));
    }

    public function test_stale_when_not_verified_within_review_window(): void
    {
        // Monthly item (interval 1, ~31-day window): 40 days untouched → stale; 20 days → fresh.
        $this->assertTrue($this->svc->isStale(40, 1));
        $this->assertFalse($this->svc->isStale(20, 1));

        // Annual item (interval 12): 40 days is well within window → fresh.
        $this->assertFalse($this->svc->isStale(40, 12));

        // Event-driven (null interval) uses a one-year window.
        $this->assertFalse($this->svc->isStale(300, null));
        $this->assertTrue($this->svc->isStale(400, null));

        // Never recorded → not flagged stale.
        $this->assertFalse($this->svc->isStale(null, 1));
    }

    public function test_status_thresholds(): void
    {
        $this->assertSame(ComplianceStatus::SET_DATES, $this->svc->status(null, false));
        $this->assertSame(ComplianceStatus::OVERDUE, $this->svc->status(-1, true));
        $this->assertSame(ComplianceStatus::DUE_30, $this->svc->status(0, true));
        $this->assertSame(ComplianceStatus::DUE_30, $this->svc->status(30, true));
        $this->assertSame(ComplianceStatus::DUE_60, $this->svc->status(31, true));
        $this->assertSame(ComplianceStatus::DUE_60, $this->svc->status(60, true));
        $this->assertSame(ComplianceStatus::ON_TRACK, $this->svc->status(61, true));
    }

    public function test_for_obligation_computes_everything(): void
    {
        // Annual obligation completed 2025-07-01 -> due 2026-07-01, 11 days out -> Due <=30.
        $o = new Obligation([
            'code' => 'C06',
            'category' => 'Procedures',
            'name' => 'Procedure / SOP review',
            'frequency_label' => 'Annual',
            'interval_months' => 12,
            'owner_role' => 'Lab Director',
            'last_completed' => '2025-07-01',
        ]);

        $derived = $this->svc->for($o, $this->today);

        $this->assertSame('2026-07-01', $derived['next_due']->toDateString());
        $this->assertSame(11, $derived['days_until_due']);
        $this->assertSame(ComplianceStatus::DUE_30, $derived['status']);
    }

    public function test_overdue_obligation_is_flagged(): void
    {
        // Monthly QC completed 4 months ago -> long overdue.
        $o = new Obligation([
            'code' => 'C12',
            'category' => 'Quality Control',
            'name' => 'QC review',
            'frequency_label' => 'Monthly',
            'interval_months' => 1,
            'owner_role' => 'Tech Supervisor',
            'last_completed' => '2026-02-01',
        ]);

        $derived = $this->svc->for($o, $this->today);

        $this->assertSame(ComplianceStatus::OVERDUE, $derived['status']);
        $this->assertLessThan(0, $derived['days_until_due']);
    }

    public function test_obligation_without_baseline_needs_dates(): void
    {
        $o = new Obligation([
            'code' => 'C01',
            'category' => 'CLIA Certification',
            'name' => 'CLIA renewal',
            'frequency_label' => 'Every 2 years',
            'interval_months' => 24,
            'owner_role' => 'Lab Director',
            'last_completed' => null,
        ]);

        $derived = $this->svc->for($o, $this->today);

        $this->assertNull($derived['next_due']);
        $this->assertSame(ComplianceStatus::SET_DATES, $derived['status']);
    }
}
