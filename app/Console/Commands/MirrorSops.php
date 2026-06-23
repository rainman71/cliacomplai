<?php

namespace App\Console\Commands;

use App\Services\Drive\GoogleDriveClient;
use Illuminate\Console\Command;

/**
 * Mirror the Rightsize-owned SOP manual (.docx files) into a "Rightsize-SOPs" folder at the Drive
 * root (the configured Shared Drive), so the master P&P documents live alongside the lab evidence.
 * Idempotent: re-running skips files already present (matched by name); it never overwrites.
 */
class MirrorSops extends Command
{
    protected $signature = 'compliance:mirror-sops {source : Local folder of .docx SOPs to mirror}
        {--parent= : Drive parent folder id (defaults to the configured root)}
        {--folder=Rightsize-SOPs : Name of the Drive folder to create/use}';

    protected $description = 'Mirror the SOP .docx manual into a folder at the Drive root';

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function handle(): int
    {
        $credentials = config('services.google.drive_credentials');
        if (! $credentials || ! is_file($credentials)) {
            $this->error('Drive service account not configured (GOOGLE_DRIVE_CREDENTIALS).');

            return self::FAILURE;
        }

        $source = $this->argument('source');
        if (! is_dir($source)) {
            $this->error("Source folder not found: {$source}");

            return self::FAILURE;
        }

        $parent = $this->option('parent') ?: config('services.google.drive_root_folder_id');
        if (! $parent) {
            $this->error('No parent folder id (pass --parent or set GOOGLE_DRIVE_ROOT_FOLDER_ID).');

            return self::FAILURE;
        }

        $client = new GoogleDriveClient($credentials);
        $folderName = (string) $this->option('folder');

        $folderId = $client->findFolder($parent, $folderName) ?? $client->createFolder($parent, $folderName);
        $this->info("Target folder '{$folderName}' = {$folderId}");

        $files = glob(rtrim($source, '/\\').DIRECTORY_SEPARATOR.'*.docx') ?: [];
        if (! $files) {
            $this->warn('No .docx files found in source.');

            return self::SUCCESS;
        }

        $uploaded = 0;
        $skipped = 0;
        foreach ($files as $path) {
            $name = basename($path);
            if ($client->findFile($folderId, $name)) {
                $this->line("  skip (exists): {$name}");
                $skipped++;

                continue;
            }
            try {
                $client->uploadFile($folderId, $name, (string) file_get_contents($path), self::DOCX_MIME);
                $this->info("  uploaded: {$name}");
                $uploaded++;
            } catch (\Throwable $e) {
                $this->error("  failed: {$name} — {$e->getMessage()}");
            }
        }

        $this->info("Done. Uploaded {$uploaded}, skipped {$skipped} (already present).");

        return self::SUCCESS;
    }
}
