<?php

namespace App\Services\Drive;

use App\Forms\FormCatalog;
use App\Models\AuditLog;
use App\Models\Completion;
use App\Models\Lab;
use App\Models\Obligation;
use App\Services\ComplianceStatusService;
use App\Support\CurrentLab;
use Carbon\CarbonImmutable;

/**
 * The "brain" of auto-ingestion: turns signed-evidence files discovered in a lab's Drive into
 * obligation completions. A filename like "CMP-173-...signed_2023.12.18.pdf" is parsed for its
 * form code (CMP-173) and signed date, mapped to its obligation (C11) via the FormCatalog, and —
 * if newer than what's on file and not already ingested — recorded as a completion that advances
 * the obligation. Idempotent via the Drive file id.
 */
class EvidenceIngestor
{
    public function __construct(
        private DriveScanner $scanner,
        private ComplianceStatusService $status,
    ) {}

    public function isConfigured(): bool
    {
        return $this->scanner->isConfigured();
    }

    /**
     * Proposed completions for a lab (latest new signed evidence per obligation). Read-only.
     *
     * @return list<array{obligation: Obligation, date: string, fileId: ?string, webLink: ?string, name: string, code: string}>
     */
    public function candidates(Lab $lab): array
    {
        if (! $this->scanner->isConfigured()) {
            return [];
        }

        $files = $this->scanner->scan($lab);

        return app(CurrentLab::class)->run($lab, function () use ($files) {
            // Keep only the latest signed file per obligation.
            $best = [];
            foreach ($files as $file) {
                $parsed = $this->parse($file->name);
                if ($parsed === null) {
                    continue;
                }
                $key = $parsed['obligation_code'];
                if (! isset($best[$key]) || $parsed['date'] > $best[$key]['date']) {
                    $best[$key] = $parsed + [
                        'fileId' => $file->fileId,
                        'webLink' => $file->webLink,
                        'name' => $file->name,
                    ];
                }
            }

            $candidates = [];
            foreach ($best as $obligationCode => $c) {
                $obligation = Obligation::where('code', $obligationCode)->first();
                if ($obligation === null) {
                    continue;
                }
                // Already ingested this exact file?
                if ($c['fileId'] !== null && Completion::where('drive_file_id', $c['fileId'])->exists()) {
                    continue;
                }
                // Only advance forward.
                $last = $obligation->last_completed?->toDateString();
                if ($last !== null && $c['date'] <= $last) {
                    continue;
                }
                $candidates[] = [
                    'obligation' => $obligation,
                    'date' => $c['date'],
                    'fileId' => $c['fileId'],
                    'webLink' => $c['webLink'],
                    'name' => $c['name'],
                    'code' => $c['code'],
                ];
            }

            return $candidates;
        });
    }

    /**
     * Apply candidates: record a completion + advance the obligation for each. Returns the applied list.
     *
     * @return list<array{obligation: Obligation, date: string, fileId: ?string, webLink: ?string, name: string, code: string}>
     */
    public function apply(Lab $lab): array
    {
        $candidates = $this->candidates($lab);

        return app(CurrentLab::class)->run($lab, function () use ($candidates) {
            foreach ($candidates as $c) {
                /** @var Obligation $obligation */
                $obligation = $c['obligation'];

                $obligation->completions()->create([
                    'lab_id' => $obligation->lab_id,
                    'completed_date' => $c['date'],
                    'document_link' => $c['webLink'],
                    'drive_file_id' => $c['fileId'],
                    'created_by' => null, // auto-ingested from Drive, not an in-app action
                ]);

                $nextDue = $this->status->nextDue(CarbonImmutable::parse($c['date']), $obligation->interval_months);
                $oldLast = $obligation->last_completed?->toDateString();
                $obligation->update([
                    'last_completed' => $c['date'],
                    'next_due' => $nextDue?->toDateString(),
                    'document_link' => $c['webLink'] ?? $obligation->document_link,
                ]);

                AuditLog::create([
                    'entity_type' => 'obligation',
                    'entity_id' => $obligation->id,
                    'field' => 'last_completed',
                    'old_value' => (string) $oldLast,
                    'new_value' => $c['date'],
                    'action' => 'evidence_ingest',
                    'changed_by' => null,
                    'changed_at' => now(),
                ]);
            }

            return $candidates;
        });
    }

    /**
     * Parse a Drive filename for its form code, signed date, and mapped obligation code.
     *
     * @return array{code: string, obligation_code: string, date: string}|null
     */
    private function parse(string $name): ?array
    {
        if (! preg_match('/\b(CMP-\d+|CMS-\d+)\b/i', $name, $codeMatch)) {
            return null;
        }
        if (! preg_match('/_signed_(\d{4})\.(\d{2})\.(\d{2})/', $name, $dateMatch)) {
            return null;
        }

        $code = strtoupper($codeMatch[1]);
        $obligationCode = FormCatalog::FORMS[$code]['obligation_code'] ?? null;
        if ($obligationCode === null) {
            return null; // a Drive form we don't map to an obligation — skip
        }

        return [
            'code' => $code,
            'obligation_code' => $obligationCode,
            'date' => "{$dateMatch[1]}-{$dateMatch[2]}-{$dateMatch[3]}",
        ];
    }
}
