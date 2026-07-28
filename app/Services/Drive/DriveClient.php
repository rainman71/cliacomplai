<?php

namespace App\Services\Drive;

/**
 * Thin port over the handful of Drive operations the filer needs. Isolating these behind an
 * interface keeps GoogleDriveFiler's orchestration (including archive-on-collision) unit-testable
 * with an in-memory fake, while the real Google API calls live in GoogleDriveClient.
 */
interface DriveClient
{
    /** Folder id of the named child folder under $parentId, or null if absent. */
    public function findFolder(string $parentId, string $name): ?string;

    /** Create a child folder and return its id. */
    public function createFolder(string $parentId, string $name): string;

    /** File id of a file with exactly $name directly in $folderId, or null. */
    public function findFile(string $folderId, string $name): ?string;

    /**
     * Upload a new PDF into $folderId. Always creates a new file (never overwrites).
     *
     * @return array{id: string, webLink: ?string}
     */
    public function uploadPdf(string $folderId, string $name, string $contents): array;

    /**
     * Move $fileId into $toFolderId and rename it (used to supersede/relocate; contents untouched).
     *
     * @return array{id: string, webLink: ?string}
     */
    public function moveRename(string $fileId, string $toFolderId, string $newName): array;

    /**
     * Direct children (folders + files) of $folderId, non-trashed, fully paginated.
     *
     * @return list<array{id: string, name: string, mimeType: string, webLink: ?string}>
     */
    public function listChildren(string $folderId): array;

    /** Raw bytes of the file with $fileId (used to pull blank form templates into the repo). */
    public function downloadFile(string $fileId): string;

    /**
     * Metadata for $folderId, or null if the service account cannot see it (bad id / not shared).
     * `driveId` is set only for items that live in a Shared Drive — used to tell a real Shared Drive
     * apart from a plain My Drive folder during lab onboarding.
     *
     * @return array{id: string, name: string, mimeType: string, driveId: ?string}|null
     */
    public function describeFolder(string $folderId): ?array;
}
