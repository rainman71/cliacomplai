<?php

namespace App\Console\Commands;

use App\Models\Lab;
use App\Services\Drive\DriveFiler;
use App\Services\Drive\DriveScanner;
use App\Services\Drive\GoogleDriveClient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

/**
 * Diagnostic for the Google Drive service-account wiring. Read-only by default; --write-test
 * uploads one clearly-named probe PDF to confirm write access (and surface the SA storage-quota
 * gotcha) without touching any compliance record.
 */
class DriveCheck extends Command
{
    protected $signature = 'compliance:drive-check {--write-test : Upload a probe PDF to confirm write access (leaves one test file)}';

    protected $description = 'Verify Google Drive service-account configuration and access';

    public function handle(DriveFiler $filer, DriveScanner $scanner): int
    {
        $path = config('services.google.drive_credentials');
        $root = config('services.google.drive_root_folder_id');

        $this->line('Credentials path:   '.($path ?: '(not set)'));
        $this->line('Credentials exists: '.($path && is_file($path) ? 'yes' : 'NO'));
        $this->line('Global root folder: '.($root ?: '(none — using per-lab roots)'));
        $this->line('Filer:              '.class_basename($filer).($filer->isConfigured() ? '  ✓ configured' : '  ✗ not configured'));
        $this->line('Scanner:            '.class_basename($scanner).($scanner->isConfigured() ? '  ✓ configured' : '  ✗ not configured'));
        $this->newLine();

        if (! $scanner->isConfigured()) {
            $this->warn('Drive not configured — check GOOGLE_DRIVE_CREDENTIALS points at the key file, then run `php artisan config:clear`.');

            return self::SUCCESS;
        }

        foreach (Lab::where('active', true)->get() as $lab) {
            try {
                $count = count($scanner->scan($lab));
                $where = $lab->drive_root_folder_id ? 'under its root folder' : '(no per-lab root set)';
                $this->info("Lab {$lab->id} \"{$lab->name}\": read OK — sees {$count} signed PDF(s) {$where}.");
            } catch (\Throwable $e) {
                $this->error("Lab {$lab->id} \"{$lab->name}\": Drive read FAILED — {$e->getMessage()}");
            }
        }

        if ($this->option('write-test')) {
            $this->newLine();
            $this->writeTest($path, $root);
        }

        return self::SUCCESS;
    }

    private function writeTest(?string $path, ?string $root): void
    {
        if (! $root) {
            $this->warn('Write test skipped — no GOOGLE_DRIVE_ROOT_FOLDER_ID set to upload into.');

            return;
        }

        try {
            $client = new GoogleDriveClient($path);
            $folderId = $client->findFolder($root, '_App Write Test') ?? $client->createFolder($root, '_App Write Test');
            $name = 'app-write-test_'.now()->format('Ymd_His').'.pdf';
            $pdf = Pdf::loadHtml('<h3>Rightsize CLIA — Drive write test</h3><p>Safe to delete. Generated '.now()->toDateTimeString().'</p>')->output();

            $result = $client->uploadPdf($folderId, $name, $pdf);

            $this->info('Write test OK — uploaded a probe PDF.');
            $this->line('  Folder: _App Write Test (under the configured root)');
            $this->line('  File:   '.$name);
            $this->line('  Link:   '.($result['webLink'] ?? '(no link returned)'));
            $this->line('  You can delete that test file/folder anytime.');
        } catch (\Throwable $e) {
            $this->error('Write test FAILED — '.$e->getMessage());
            if (stripos($e->getMessage(), 'quota') !== false || stripos($e->getMessage(), 'storage') !== false) {
                $this->warn('This looks like the service-account storage-quota limit. Fix: put the compliance tree in a Shared Drive and add the service account as a member (Content manager), or use domain-wide delegation.');
            }
        }
    }
}
