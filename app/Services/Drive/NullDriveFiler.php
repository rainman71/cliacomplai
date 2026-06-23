<?php

namespace App\Services\Drive;

use App\Models\Completion;
use App\Models\Obligation;
use Illuminate\Support\Facades\Log;

/**
 * Default filer for when no Drive credentials are configured. Computes the canonical
 * destination (folder + filename) and logs it, but doesn't touch Google. The existing
 * document link on the obligation is preserved.
 */
class NullDriveFiler implements DriveFiler
{
    public function __construct(private DriveNaming $naming) {}

    public function isConfigured(): bool
    {
        return false;
    }

    public function fileCompletion(Obligation $obligation, Completion $completion): FiledDocument
    {
        $date = optional($completion->completed_date)->toDateString() ?? now()->toDateString();
        $folderPath = implode('/', $this->naming->folderSegments($obligation, $date));
        $filename = $this->naming->filename($obligation, $date);

        Log::info('[Drive] would file completion (no Drive credentials configured)', [
            'obligation' => $obligation->code,
            'folder' => $folderPath,
            'filename' => $filename,
        ]);

        return new FiledDocument(
            folderPath: $folderPath,
            filename: $filename,
            fileId: null,
            webLink: $obligation->document_link, // keep whatever link already exists
        );
    }

    public function fileGeneratedPdf(Obligation $obligation, Completion $completion, string $contents, ?string $fallbackLink = null): FiledDocument
    {
        $date = optional($completion->completed_date)->toDateString() ?? now()->toDateString();
        $folderPath = implode('/', $this->naming->folderSegments($obligation, $date));
        $filename = $this->naming->filename($obligation, $date);

        Log::info('[Drive] would upload generated PDF (no Drive credentials configured)', [
            'obligation' => $obligation->code,
            'folder' => $folderPath,
            'filename' => $filename,
            'bytes' => strlen($contents),
        ]);

        return new FiledDocument($folderPath, $filename, null, $fallbackLink);
    }
}
