<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Completion;
use App\Models\Obligation;
use App\Models\SignatureRequest;
use App\Models\SignatureRequestSigner;
use App\Services\Drive\DriveFiler;
use App\Services\Signature\SignatureProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Drives the signature state machine (plan Feature B). Works with whatever
 * SignatureProvider is bound (ManualGoogleESignature today, DocuSign later).
 */
class SignatureService
{
    public function __construct(
        private SignatureProvider $provider,
        private ComplianceStatusService $status,
        private DriveFiler $drive,
    ) {}

    /** The open (not yet completed) signature request for an obligation, if any. */
    public function openRequest(Obligation $obligation): ?SignatureRequest
    {
        return $obligation->signatureRequests()
            ->whereIn('status', ['out_for_signature', 'partially_signed'])
            ->latest('id')
            ->first();
    }

    /**
     * Start a signature run. No-op (returns the existing one) if already open.
     */
    public function sendForSignature(Obligation $obligation): SignatureRequest
    {
        if ($existing = $this->openRequest($obligation)) {
            return $existing;
        }

        return DB::transaction(function () use ($obligation) {
            $request = $obligation->signatureRequests()->create([
                'deadline' => $obligation->next_due, // may be null if no baseline yet
                'status' => 'out_for_signature',
            ]);

            // Provider records sent_date and seeds the per-signer rows.
            $this->provider->send($request);

            $this->setObligationSignature($obligation, 'out_for_signature');

            return $request->fresh('signers');
        });
    }

    public function markSigned(SignatureRequestSigner $signer): void
    {
        $signer->update(['status' => 'signed', 'signed_date' => now()]);
        $this->recompute($signer->signatureRequest);
    }

    public function markRejected(SignatureRequestSigner $signer): void
    {
        $signer->update(['status' => 'rejected', 'signed_date' => null]);
        $this->recompute($signer->signatureRequest);
    }

    /** Whether every required signer has signed. */
    public function allSigned(SignatureRequest $request): bool
    {
        $request->loadMissing('signers');
        $total = $request->signers->count();

        return $total > 0 && $request->signers->every(fn ($s) => $s->status === 'signed');
    }

    /**
     * Finalize: record a completion (history), advance the obligation's dates, and
     * close the request. Mirrors what the DocuSign webhook would do automatically.
     */
    public function complete(SignatureRequest $request, ?string $completedDate = null): Completion
    {
        return DB::transaction(function () use ($request, $completedDate) {
            $obligation = $request->obligation;
            $date = $completedDate ?: CarbonImmutable::now()->toDateString();

            $completion = $obligation->completions()->create([
                'completed_date' => $date,
                'document_link' => $obligation->document_link,
                'created_by' => auth()->id(),
            ]);

            // File the signed evidence into the standard Drive folder (no-op until
            // Drive credentials are configured) and record where it landed.
            $filed = $this->drive->fileCompletion($obligation, $completion);
            $completion->update([
                'drive_file_id' => $filed->fileId,
                'document_link' => $filed->webLink ?? $completion->document_link,
            ]);

            $request->update(['status' => 'complete', 'completion_id' => $completion->id]);

            // Advance the schedule.
            $nextDue = $this->status->nextDue(CarbonImmutable::parse($date), $obligation->interval_months);
            $oldLast = optional($obligation->last_completed)->toDateString();
            $obligation->update([
                'last_completed' => $date,
                'next_due' => $nextDue?->toDateString(),
                'signature_status' => 'complete',
                'document_link' => $filed->webLink ?? $obligation->document_link,
            ]);

            $this->audit($obligation->id, 'last_completed', $oldLast, $date, 'signature_complete');
            $this->audit($obligation->id, 'signature_status', null, 'complete', 'signature_complete');

            return $completion;
        });
    }

    /** Recompute request + obligation status from current signer states. */
    private function recompute(SignatureRequest $request): void
    {
        $request->loadMissing('signers');
        $signed = $request->signers->where('status', 'signed')->count();
        $total = $request->signers->count();

        $status = $signed === 0 ? 'out_for_signature' : 'partially_signed';
        $request->update(['status' => $status]);
        $this->setObligationSignature($request->obligation, $status);
    }

    private function setObligationSignature(Obligation $obligation, string $status): void
    {
        $old = $obligation->signature_status;
        if ($old === $status) {
            return;
        }
        $obligation->update(['signature_status' => $status]);
        $this->audit($obligation->id, 'signature_status', $old, $status, 'signature_update');
    }

    private function audit(int $obligationId, string $field, ?string $old, ?string $new, string $action): void
    {
        AuditLog::create([
            'entity_type' => 'obligation',
            'entity_id' => $obligationId,
            'field' => $field,
            'old_value' => (string) $old,
            'new_value' => (string) $new,
            'action' => $action,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }
}
