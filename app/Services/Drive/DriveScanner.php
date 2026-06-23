<?php

namespace App\Services\Drive;

use App\Models\Lab;

/**
 * Reads a lab's Drive area for signed-evidence files (the "eyes" of auto-ingestion).
 *
 *  - NullDriveScanner (default): not configured; returns nothing.
 *  - GoogleDriveScanner: lists PDFs under the lab's Drive root via the service account.
 *
 * Parsing/matching is done by EvidenceIngestor, not here.
 *
 * @return list<DiscoveredFile>
 */
interface DriveScanner
{
    public function isConfigured(): bool;

    /** @return list<DiscoveredFile> */
    public function scan(Lab $lab): array;
}
