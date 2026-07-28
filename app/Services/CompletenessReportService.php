<?php

namespace App\Services;

use App\Models\Obligation;
use Illuminate\Support\Collection;

/**
 * Compiles the audit-ready completeness report (plan Feature C3): for each obligation,
 * whether it's completed, its last completion date, required signers, document link,
 * and completion history. Used by the in-app view, CSV export, and PDF export so the
 * three never drift.
 */
class CompletenessReportService
{
    public function __construct(private ComplianceStatusService $status) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        return Obligation::with(['requiredSigners', 'completions'])
            ->orderBy('code')
            ->get()
            ->map(function (Obligation $o) {
                $d = $this->status->for($o);

                return [
                    'code' => $o->code,
                    'category' => $o->category,
                    'name' => $o->name,
                    'owner_role' => $o->owner_role,
                    'frequency' => $o->frequency_label,
                    'interval_months' => $o->interval_months,
                    'completed' => $o->last_completed !== null,
                    'last_completed' => optional($o->last_completed)->toDateString(),
                    'next_due' => $d['next_due']?->toDateString(),
                    'days_until_due' => $d['days_until_due'],
                    'status' => $d['status']->label(),
                    'signature_status' => $this->signatureLabel($o->signature_status),
                    'required_signers' => $o->requiredSigners->pluck('signer_role')->implode('; '),
                    'document_link' => $o->document_link,
                    'history' => $o->completions->map(fn ($c) => [
                        'date' => optional($c->completed_date)->toDateString(),
                        'link' => $c->document_link,
                    ])->all(),
                ];
            });
    }

    /** Summary counts for the report header. */
    public function summary(): array
    {
        $rows = $this->rows();

        return [
            'total' => $rows->count(),
            'completed' => $rows->where('completed', true)->count(),
            'missing' => $rows->where('completed', false)->count(),
            'with_links' => $rows->whereNotNull('document_link')->where('document_link', '!=', '')->count(),
        ];
    }

    private function signatureLabel(string $status): string
    {
        return match ($status) {
            'out_for_signature' => 'Out for Signature',
            'partially_signed' => 'Partially Signed',
            'complete' => 'Complete',
            default => 'Not started',
        };
    }
}
