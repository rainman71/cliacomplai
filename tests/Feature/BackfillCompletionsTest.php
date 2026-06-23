<?php

namespace Tests\Feature;

use App\Models\Obligation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillCompletionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_baseline_completions_idempotently(): void
    {
        $lab = $this->makeLab();

        // An obligation with a migrated last_completed date but no completion row.
        $c01 = Obligation::where('code', 'C01')->first();
        $c01->update(['last_completed' => '2025-01-01', 'document_link' => 'https://drive.example/c01']);
        $this->assertSame(0, $c01->completions()->count());

        $this->artisan('compliance:backfill-completions')->assertExitCode(0);

        $c01->refresh();
        $this->assertSame(1, $c01->completions()->count());
        $completion = $c01->completions()->first();
        $this->assertSame('2025-01-01', $completion->completed_date->toDateString());
        $this->assertSame('https://drive.example/c01', $completion->document_link);
        $this->assertNull($completion->created_by); // marked as a migrated baseline, not an in-app action

        // Running again creates nothing new (idempotent).
        $this->artisan('compliance:backfill-completions');
        $this->assertSame(1, $c01->fresh()->completions()->count());

        // Obligations without a baseline date are left alone.
        $this->assertSame(0, Obligation::where('code', 'C02')->first()->completions()->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $lab = $this->makeLab();
        Obligation::where('code', 'C01')->first()->update(['last_completed' => '2025-01-01']);

        $this->artisan('compliance:backfill-completions --dry-run')->assertExitCode(0);

        $this->assertSame(0, Obligation::where('code', 'C01')->first()->completions()->count());
    }
}
