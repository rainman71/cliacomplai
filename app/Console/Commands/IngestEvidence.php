<?php

namespace App\Console\Commands;

use App\Models\Lab;
use App\Services\Drive\EvidenceIngestor;
use Illuminate\Console\Command;

/**
 * Scans each active lab's Drive for signed evidence and advances matching obligations.
 * Preview by default (safe); pass --apply to write. Live only when the Google service
 * account is configured; otherwise the scanner sees nothing.
 */
class IngestEvidence extends Command
{
    protected $signature = 'compliance:ingest-evidence {--apply : Write the detected completions (default: preview only)}';

    protected $description = "Auto-ingest signed evidence from Drive and advance matching obligations";

    public function handle(EvidenceIngestor $ingestor): int
    {
        if (! $ingestor->isConfigured()) {
            $this->warn('Drive scanner not configured (no service-account credentials) — nothing to ingest.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $total = 0;

        foreach (Lab::where('active', true)->get() as $lab) {
            $candidates = $ingestor->candidates($lab);

            foreach ($candidates as $c) {
                $this->line("lab {$lab->id}  {$c['obligation']->code} <- {$c['code']} @ {$c['date']}  ({$c['name']})"
                    . ($apply ? '' : '  [preview]'));
            }

            if ($apply && $candidates) {
                $ingestor->apply($lab);
            }

            $total += count($candidates);
        }

        $this->info($apply
            ? "{$total} obligation(s) advanced from Drive evidence."
            : "{$total} candidate(s) detected (preview). Re-run with --apply to write.");

        return self::SUCCESS;
    }
}
