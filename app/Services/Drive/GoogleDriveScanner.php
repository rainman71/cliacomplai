<?php

namespace App\Services\Drive;

use App\Models\Lab;

/**
 * Scans a lab's Drive subtree (recursively) for signed-evidence PDFs. Scoped strictly to the
 * lab's own drive_root_folder_id — if a lab has no root configured it returns nothing, so labs
 * never ingest each other's evidence. Traversal runs over the DriveClient port (real
 * GoogleDriveClient in prod, FakeDriveClient in tests), so the recursion is unit-testable.
 */
class GoogleDriveScanner implements DriveScanner
{
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public function __construct(private DriveClient $client) {}

    public function isConfigured(): bool
    {
        return true; // only constructed when credentials are present (see AppServiceProvider)
    }

    public function scan(Lab $lab): array
    {
        $root = $lab->drive_root_folder_id;
        if (empty($root)) {
            return []; // no per-lab root → nothing to scan (prevents cross-lab ingestion)
        }

        $found = [];
        $queue = [$root];
        $seen = [];

        while ($queue) {
            $folderId = array_shift($queue);
            if (isset($seen[$folderId])) {
                continue; // guard against cycles / shortcuts
            }
            $seen[$folderId] = true;

            foreach ($this->client->listChildren($folderId) as $child) {
                if ($child['mimeType'] === self::FOLDER_MIME) {
                    $queue[] = $child['id'];
                } elseif (str_contains($child['name'], '_signed_')) {
                    $found[] = new DiscoveredFile($child['name'], $child['id'], $child['webLink']);
                }
            }
        }

        return $found;
    }
}
