<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\Obligation;
use App\Services\Drive\DiscoveredFile;
use App\Services\Drive\DriveScanner;
use App\Services\Drive\EvidenceIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceIngestionTest extends TestCase
{
    use RefreshDatabase;

    /** Bind a fake "configured" scanner returning the given files. */
    private function fakeScanner(array $files): void
    {
        $this->app->instance(DriveScanner::class, new class($files) implements DriveScanner {
            public function __construct(private array $files) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function scan(Lab $lab): array
            {
                return $this->files;
            }
        });
    }

    public function test_ingests_signed_evidence_and_advances_matched_obligations(): void
    {
        $lab = $this->makeLab();
        $this->fakeScanner([
            new DiscoveredFile('CMP-173-TBR Laboratory Safety Checklist.pdf_signed_2026.06.20.10.00.00.pdf', 'file-c11', 'https://drive/c11'),
            new DiscoveredFile('CMP-130 QC Review (MARCH IR500-2026).pdf_signed_2026.06.21.09.00.00.pdf', 'file-c12', 'https://drive/c12'),
            new DiscoveredFile('CMP-116 Equipment Log.pdf_signed_2026.06.10.00.00.00.pdf', 'file-x', null), // unmapped code → skipped
            new DiscoveredFile('random notes.pdf', 'file-y', null), // no code/date → skipped
        ]);

        $applied = app(EvidenceIngestor::class)->apply($lab);
        $this->assertCount(2, $applied);

        $c11 = Obligation::where('code', 'C11')->first();
        $this->assertSame('2026-06-20', $c11->last_completed->toDateString());
        $this->assertSame('https://drive/c11', $c11->document_link);
        $this->assertSame(1, $c11->completions()->where('drive_file_id', 'file-c11')->count());
        $this->assertNull($c11->completions()->first()->created_by); // auto-ingested

        $c12 = Obligation::where('code', 'C12')->first();
        $this->assertSame('2026-06-21', $c12->last_completed->toDateString());

        // The unmapped equipment log did not touch C07.
        $this->assertNull(Obligation::where('code', 'C07')->first()->last_completed);
    }

    public function test_ingestion_only_advances_forward_and_is_idempotent(): void
    {
        $lab = $this->makeLab();

        // C11 already has a newer completion than the evidence → the older file is ignored.
        Obligation::where('code', 'C11')->update(['last_completed' => '2026-07-01']);
        $this->fakeScanner([
            new DiscoveredFile('CMP-173 Safety.pdf_signed_2026.06.20.10.00.00.pdf', 'file-old', 'l'),
        ]);
        $this->assertCount(0, app(EvidenceIngestor::class)->apply($lab));

        // A genuinely newer file is ingested once; re-running ingests nothing (same Drive file id).
        $this->fakeScanner([
            new DiscoveredFile('CMP-173 Safety.pdf_signed_2026.08.01.10.00.00.pdf', 'file-new', 'l'),
        ]);
        $this->assertCount(1, app(EvidenceIngestor::class)->apply($lab));
        $this->assertSame('2026-08-01', Obligation::where('code', 'C11')->first()->last_completed->toDateString());

        $this->assertCount(0, app(EvidenceIngestor::class)->apply($lab)); // idempotent
        $this->assertSame(1, Obligation::where('code', 'C11')->first()->completions()->where('drive_file_id', 'file-new')->count());
    }

    public function test_command_previews_by_default_and_writes_with_apply(): void
    {
        $lab = $this->makeLab();
        $this->fakeScanner([
            new DiscoveredFile('CMP-172 Reference Lab Approval.pdf_signed_2026.06.20.00.00.00.pdf', 'file-c10', 'https://drive/c10'),
        ]);

        // Preview: nothing written.
        $this->artisan('compliance:ingest-evidence')->assertExitCode(0);
        $this->assertNull(Obligation::where('code', 'C10')->first()->last_completed);

        // Apply: obligation advanced.
        $this->artisan('compliance:ingest-evidence --apply')->assertExitCode(0);
        $this->assertSame('2026-06-20', Obligation::where('code', 'C10')->first()->last_completed->toDateString());
    }

    public function test_null_scanner_ingests_nothing(): void
    {
        $lab = $this->makeLab();
        // Default binding is the Null scanner (no credentials in tests).
        $this->assertFalse(app(EvidenceIngestor::class)->isConfigured());
        $this->assertCount(0, app(EvidenceIngestor::class)->candidates($lab));
        $this->artisan('compliance:ingest-evidence')->assertExitCode(0);
    }
}
