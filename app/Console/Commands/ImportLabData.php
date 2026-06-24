<?php

namespace App\Console\Commands;

use App\Models\Lab;
use App\Models\Obligation;
use Illuminate\Console\Command;

/**
 * Merge a lab export (from compliance:export-lab-data) onto a TARGET lab in this environment:
 * updates the lab's profile/CLIA/Drive-root, and each obligation's dates/links/status matched by
 * CODE. Non-destructive to the template — obligations the target has but the export doesn't (e.g.
 * a newer C17) are left untouched; codes in the export with no matching target obligation are
 * reported and skipped. Run `compliance:backfill-completions` afterward to rebuild history.
 */
class ImportLabData extends Command
{
    protected $signature = 'compliance:import-lab-data {lab : Target lab id or exact name} {path : JSON from compliance:export-lab-data} {--force : Required — overwrites the target lab\'s obligation dates/links}';

    protected $description = "Merge a lab export onto a target lab (profile + obligation dates/links by code)";

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }
        if (! $this->option('force')) {
            $this->error("This overwrites the target lab's obligation dates/links. Re-run with --force.");

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload) || ! isset($payload['obligations'])) {
            $this->error('Could not parse the export file.');

            return self::FAILURE;
        }

        $lab = $this->resolveLab($this->argument('lab'));
        if (! $lab) {
            $this->error('Target lab not found: '.$this->argument('lab'));

            return self::FAILURE;
        }

        // 1. Lab-level fields (only overwrite when the export carries a value).
        $labData = $payload['lab'] ?? [];
        foreach (['clia_number', 'timezone', 'drive_root_folder_id'] as $f) {
            if (! empty($labData[$f])) {
                $lab->setAttribute($f, $labData[$f]);
            }
        }
        if (! empty($labData['profile'])) {
            $lab->profile = $labData['profile'];
        }
        $lab->save();
        $this->info(sprintf('Updated lab "%s" (profile + identity).', $lab->name));

        // 2. Obligation data, matched by code.
        $matched = 0;
        $skipped = [];
        foreach ($payload['obligations'] as $code => $fields) {
            $o = Obligation::withoutGlobalScopes()->where('lab_id', $lab->id)->where('code', $code)->first();
            if (! $o) {
                $skipped[] = $code;

                continue;
            }
            foreach (ExportLabData::OBLIGATION_FIELDS as $f) {
                if (array_key_exists($f, $fields)) {
                    $o->setAttribute($f, $fields[$f]);
                }
            }
            $o->save();
            $matched++;
        }

        $this->info("Obligations updated by code: {$matched}.");
        if ($skipped) {
            $this->warn('Codes in export with no matching obligation here (skipped): '.implode(', ', $skipped));
        }
        $targetOnly = Obligation::withoutGlobalScopes()->where('lab_id', $lab->id)
            ->whereNotIn('code', array_keys($payload['obligations']))->pluck('code')->all();
        if ($targetOnly) {
            $this->line('Target obligations not in export (left untouched): '.implode(', ', $targetOnly));
        }

        $this->info('Done. Next: php artisan compliance:backfill-completions to rebuild history.');

        return self::SUCCESS;
    }

    private function resolveLab(string $idOrName): ?Lab
    {
        $q = Lab::withoutGlobalScopes();

        return is_numeric($idOrName) ? $q->find((int) $idOrName) : $q->where('name', $idOrName)->first();
    }
}
