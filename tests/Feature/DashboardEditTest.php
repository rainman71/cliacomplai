<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lab;
use App\Models\Obligation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardEditTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lab = $this->makeLab();
        $this->actingInLab($this->lab, 'compliance_specialist'); // an editor + manager
    }

    public function test_editing_last_completed_persists_and_recomputes_next_due(): void
    {
        $c03 = Obligation::where('code', 'C03')->first(); // interval 6 months

        Livewire::test('compliance-dashboard', ['lab' => $this->lab])
            ->set("form.{$c03->id}.last_completed", '2026-01-15');

        $c03->refresh();
        $this->assertSame('2026-01-15', $c03->last_completed->toDateString());
        $this->assertSame('2026-07-15', $c03->next_due->toDateString());
    }

    public function test_edit_writes_an_audit_log_entry(): void
    {
        $c03 = Obligation::where('code', 'C03')->first();

        Livewire::test('compliance-dashboard', ['lab' => $this->lab])
            ->set("form.{$c03->id}.document_link", 'https://drive.google.com/abc');

        $this->assertDatabaseHas('audit_log', [
            'lab_id' => $this->lab->id,
            'entity_type' => 'obligation',
            'entity_id' => $c03->id,
            'field' => 'document_link',
            'new_value' => 'https://drive.google.com/abc',
        ]);
    }

    public function test_no_audit_entry_when_value_unchanged(): void
    {
        $c01 = Obligation::where('code', 'C01')->first();

        Livewire::test('compliance-dashboard', ['lab' => $this->lab])
            ->set("form.{$c01->id}.signature_status", 'not_started');

        $this->assertSame(0, AuditLog::allLabs()->count());
    }

    public function test_overdue_obligation_appears_on_overdue_tab(): void
    {
        $c12 = Obligation::where('code', 'C12')->first();
        $c12->update(['last_completed' => '2026-01-01', 'next_due' => '2026-02-01']);

        Livewire::test('compliance-dashboard', ['lab' => $this->lab])
            ->call('setTab', 'overdue')
            ->assertSee('QC review')
            ->assertSee('OVERDUE');
    }
}
