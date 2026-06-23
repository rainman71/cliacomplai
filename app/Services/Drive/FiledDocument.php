<?php

namespace App\Services\Drive;

/**
 * Result of filing a completion's evidence to Drive (or, for the Null filer,
 * the canonical location it *would* be filed to).
 */
readonly class FiledDocument
{
    public function __construct(
        public string $folderPath,   // e.g. "2026 Annual Documents/Proficiency Testing"
        public string $filename,     // convention filename
        public ?string $fileId = null,   // Drive file/folder id, if created
        public ?string $webLink = null,  // viewable Drive URL, if available
    ) {}
}
