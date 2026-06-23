<?php

namespace App\Console\Commands;

use App\Forms\FormCatalog;
use App\Services\Drive\GoogleDriveClient;
use Illuminate\Console\Command;

/**
 * One-off (re-runnable) puller for the blank official form templates. Downloads the flat PDF
 * for each catalog form that declares a `template_drive_id` into resources/form-templates/{CODE}.pdf,
 * so the overlay renderer fills the real document offline (no Drive fetch at render time).
 */
class PullFormTemplates extends Command
{
    protected $signature = 'compliance:pull-templates {code? : Limit to a single form code (e.g. CMP-173)}';

    protected $description = 'Download blank official form templates from Drive into resources/form-templates';

    public function handle(): int
    {
        $credentials = config('services.google.drive_credentials');
        if (! $credentials || ! is_file($credentials)) {
            $this->error('Drive service account not configured (GOOGLE_DRIVE_CREDENTIALS).');

            return self::FAILURE;
        }

        $client = new GoogleDriveClient($credentials);
        $dir = resource_path('form-templates');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $only = $this->argument('code');
        $pulled = 0;

        foreach (FormCatalog::FORMS as $code => $def) {
            if ($only && $code !== $only) {
                continue;
            }
            $driveId = $def['template_drive_id'] ?? null;
            if (! $driveId) {
                continue;
            }

            $this->line("Pulling {$code} ({$driveId})…");
            try {
                $bytes = $client->downloadFile($driveId);
            } catch (\Throwable $e) {
                $this->error("  failed: {$e->getMessage()}");

                continue;
            }

            if ($bytes === '' || ! str_starts_with($bytes, '%PDF')) {
                $this->error('  not a PDF (empty or wrong mime) — skipped.');

                continue;
            }

            $path = "{$dir}/{$code}.pdf";
            file_put_contents($path, $bytes);
            $this->info('  saved '.number_format(strlen($bytes)).' bytes → '.$path);
            $pulled++;
        }

        $this->info("Done. {$pulled} template(s) pulled.");

        return self::SUCCESS;
    }
}
