<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\Obligation;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SignatureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;
    private SignatureService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lab = $this->makeLab();
        $this->svc = app(SignatureService::class);
    }

    private function c02(): Obligation
    {
        return Obligation::where('code', 'C02')->first(); // 2 signers, interval 4
    }

    public function test_send_creates_request_and_seeds_signers(): void
    {
        $req = $this->svc->sendForSignature($this->c02());

        $this->assertSame('out_for_signature', $req->status);
        $this->assertSame($this->lab->id, $req->lab_id);
        $this->assertCount(2, $req->signers);
        $this->assertSame('out_for_signature', $this->c02()->signature_status);
    }

    public function test_send_is_idempotent(): void
    {
        $a = $this->svc->sendForSignature($this->c02());
        $b = $this->svc->sendForSignature($this->c02());

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $this->c02()->signatureRequests()->count());
    }

    public function test_marking_signers_progresses_status(): void
    {
        $req = $this->svc->sendForSignature($this->c02());

        $this->svc->markSigned($req->signers->first());
        $this->assertFalse($this->svc->allSigned($req->fresh()));
        $this->assertSame('partially_signed', $this->c02()->signature_status);

        $this->svc->markSigned($req->signers->last());
        $this->assertTrue($this->svc->allSigned($req->fresh()));
    }

    public function test_complete_records_completion_and_advances_dates(): void
    {
        $this->travelTo('2026-06-20');
        $obligation = $this->c02();
        $req = $this->svc->sendForSignature($obligation);
        foreach ($req->signers as $s) {
            $this->svc->markSigned($s);
        }

        $completion = $this->svc->complete($req->fresh('signers'));

        $obligation->refresh();
        $this->assertSame('2026-06-20', $obligation->last_completed->toDateString());
        $this->assertSame('2026-10-20', $obligation->next_due->toDateString());
        $this->assertSame('complete', $obligation->signature_status);
        $this->assertSame($this->lab->id, $completion->lab_id);
    }

    public function test_completion_logs_audit_entries(): void
    {
        $req = $this->svc->sendForSignature($this->c02());
        foreach ($req->signers as $s) {
            $this->svc->markSigned($s);
        }
        $this->svc->complete($req->fresh('signers'));

        $this->assertDatabaseHas('audit_log', [
            'lab_id' => $this->lab->id,
            'entity_id' => $this->c02()->id,
            'field' => 'signature_status',
            'new_value' => 'complete',
            'action' => 'signature_complete',
        ]);
    }

    public function test_full_flow_through_livewire_component(): void
    {
        $c02 = $this->c02();
        $this->actingInLab($this->lab, 'compliance_specialist');

        $component = Livewire::test('compliance-dashboard', ['lab' => $this->lab])
            ->call('sendForSignature', $c02->id)
            ->assertSet('tab', 'awaiting');

        $req = $c02->signatureRequests()->latest('id')->first();
        foreach ($req->signers as $s) {
            $component->call('markSigned', $s->id);
        }
        $component->call('markComplete', $req->id);

        $this->assertSame('complete', $c02->fresh()->signature_status);
        $this->assertNotNull($c02->fresh()->last_completed);
    }
}
