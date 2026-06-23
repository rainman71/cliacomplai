<?php

namespace App\Services\Drive;

use App\Models\Completion;
use App\Models\Obligation;

/**
 * Files a completion's signed evidence into the standard Drive folder structure.
 *
 *  - NullDriveFiler (default): computes the canonical folder/filename and records it,
 *    no network. Used until Drive credentials are configured.
 *  - GoogleDriveFiler: ensures the folder tree exists and moves/renames the file.
 */
interface DriveFiler
{
    public function isConfigured(): bool;

    public function fileCompletion(Obligation $obligation, Completion $completion): FiledDocument;

    /**
     * File a generated PDF (raw bytes) into the standard folder structure — used by the in-app
     * form wizards, where the evidence is produced by the app rather than downloaded from Drive.
     * NullDriveFiler computes the canonical location only; GoogleDriveFiler uploads the bytes.
     */
    public function fileGeneratedPdf(Obligation $obligation, Completion $completion, string $contents, ?string $fallbackLink = null): FiledDocument;
}
