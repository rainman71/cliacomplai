<?php

namespace App\Console\Commands;

use App\Models\Completion;
use App\Models\Obligation;
use Illuminate\Console\Command;

/**
 * Reconciles migrated baseline data: an obligation may carry a `last_completed` date (loaded
 * directly during migration) without a matching `completions` row. The data model treats the
 * completions table as the history of record, so this materializes one baseline completion per
 * such obligation — faithfully recording the known last-completed date + its evidence link,
 * not fabricating a new event (created_by stays null to mark it as a migrated baseline).
 *
 * Idempotent: only creates a completion where last_completed is set AND no completion exists yet.
 */
class BackfillBaselineCompletions extends Command
{
    protected $signature = 'compliance:backfill-completions {--dry-run : Report what would change without writing}';

    protected $description = 'Create baseline completion rows for obligations that have a last_completed date but no completion history';

    public function handle(): int
    {
        $created = 0;
        $dry = (bool) $this->option('dry-run');

        foreach (Obligation::allLabs()->whereNotNull('last_completed')->get() as $obligation) {
            if (Completion::allLabs()->where('obligation_id', $obligation->id)->exists()) {
                continue;
            }

            $date = $obligation->last_completed->toDateString();
            $this->line("lab {$obligation->lab_id}  {$obligation->code}  baseline completion @ {$date}"
                . ($dry ? '  (dry-run)' : ''));

            if (! $dry) {
                Completion::create([
                    'lab_id' => $obligation->lab_id,
                    'obligation_id' => $obligation->id,
                    'completed_date' => $date,
                    'document_link' => $obligation->document_link,
                    'created_by' => null, // migrated baseline, not an in-app action
                ]);
            }

            $created++;
        }

        $this->info($dry
            ? "{$created} baseline completion(s) would be created."
            : "{$created} baseline completion(s) created.");

        return self::SUCCESS;
    }
}
