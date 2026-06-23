<?php

namespace App\Console\Commands;

use App\Services\Drive\GoogleDriveClient;
use Illuminate\Console\Command;

/**
 * Trash the app's own test-filing artifacts left in the Drive from development/test runs:
 * the "_App Write Test" folder and the obligation-coded "..._signed.pdf" / "..._signed.superseded_*"
 * PDFs the filer produced. Scoped by a created-after cutoff so real historical evidence (earlier,
 * named "..._signed_<date>.pdf") is never touched. Dry-run by default; pass --apply to trash
 * (Drive trash is recoverable ~30 days; folders/contents go together).
 */
class DriveCleanup extends Command
{
    protected $signature = 'compliance:drive-cleanup {--apply : Actually trash (default is a dry-run)}
        {--since=2026-06-21 : Only consider artifacts created on/after this date (YYYY-MM-DD)}';

    protected $description = 'Trash stray app test-filing artifacts from the Drive (dry-run by default)';

    public function handle(): int
    {
        $credentials = config('services.google.drive_credentials');
        if (! $credentials || ! is_file($credentials)) {
            $this->error('Drive service account not configured.');

            return self::FAILURE;
        }

        $client = new GoogleDriveClient($credentials);
        $apply = (bool) $this->option('apply');
        $since = $this->option('since').'T00:00:00Z';

        // Test-filing artifacts: the write-test folder, and generated signed/superseded PDFs created
        // in the test window. Real evidence is older and named "..._signed_<date>.pdf" (underscore+date).
        $queries = [
            "name = '_App Write Test' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            "(name contains '_signed.pdf' or name contains '_signed.superseded_') and createdTime >= '{$since}' and trashed = false",
        ];

        $targets = [];
        foreach ($queries as $q) {
            foreach ($client->query($q) as $f) {
                $targets[$f['id']] = $f; // dedupe by id
            }
        }

        if (! $targets) {
            $this->info('Nothing to clean up.');

            return self::SUCCESS;
        }

        $this->line(($apply ? 'Trashing' : 'DRY-RUN — would trash').' '.count($targets).' item(s):');
        foreach ($targets as $f) {
            $kind = $f['mimeType'] === 'application/vnd.google-apps.folder' ? 'folder' : 'file';
            $this->line("  [{$kind}] {$f['name']}");
            if ($apply) {
                try {
                    $client->trashFile($f['id']);
                } catch (\Throwable $e) {
                    $this->error("    failed: {$e->getMessage()}");
                }
            }
        }

        $this->info($apply
            ? 'Done — moved to Drive trash (recoverable ~30 days).'
            : 'Dry-run only. Re-run with --apply to trash.');

        return self::SUCCESS;
    }
}
