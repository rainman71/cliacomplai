<?php

namespace App\Services\Drive;

use App\Models\Completion;
use App\Models\Obligation;

/**
 * Real Drive filer. Ensures the standard folder tree exists and files evidence into it via a
 * DriveClient port. Filing never overwrites: a new PDF is always created, and if a file with the
 * same canonical name already exists (a same-day re-file / correction), the existing one is moved
 * into an "Archived" subfolder (renamed, superseded) before the new one is written — so nothing is
 * lost and the current folder holds exactly one current file per obligation/date.
 *
 * Activated when GOOGLE_DRIVE_CREDENTIALS points at a valid service-account key (see AppServiceProvider).
 */
class GoogleDriveFiler implements DriveFiler
{
    public const ARCHIVE_FOLDER = 'Archived';

    public function __construct(
        private DriveNaming $naming,
        private DriveClient $client,
        private ?string $rootFolderId,
    ) {}

    public function isConfigured(): bool
    {
        return true; // only constructed when credentials are present
    }

    public function fileCompletion(Obligation $obligation, Completion $completion): FiledDocument
    {
        $date = optional($completion->completed_date)->toDateString() ?? now()->toDateString();
        $segments = $this->naming->folderSegments($obligation, $date);
        $filename = $this->naming->filename($obligation, $date);
        $folderId = $this->ensureFolderPath($segments);

        // Move + rename a file we already own (e.g. an e-signature/DocuSign output) into place.
        if ($completion->drive_file_id) {
            $moved = $this->client->moveRename($completion->drive_file_id, $folderId, $filename);

            return new FiledDocument($this->pathLabel($segments), $filename, $moved['id'], $moved['webLink']);
        }

        return new FiledDocument(
            folderPath: $this->pathLabel($segments),
            filename: $filename,
            fileId: $folderId,
            webLink: "https://drive.google.com/drive/folders/{$folderId}",
        );
    }

    public function fileGeneratedPdf(Obligation $obligation, Completion $completion, string $contents, ?string $fallbackLink = null): FiledDocument
    {
        $date = optional($completion->completed_date)->toDateString() ?? now()->toDateString();
        $segments = $this->naming->folderSegments($obligation, $date);
        $filename = $this->naming->filename($obligation, $date);
        $folderId = $this->ensureFolderPath($segments);

        // Same-name collision → preserve the existing file in an Archived subfolder before writing new.
        $existingId = $this->client->findFile($folderId, $filename);
        if ($existingId !== null) {
            $archiveId = $this->client->findFolder($folderId, self::ARCHIVE_FOLDER)
                ?? $this->client->createFolder($folderId, self::ARCHIVE_FOLDER);
            $this->client->moveRename($existingId, $archiveId, $this->supersededName($filename));
        }

        $created = $this->client->uploadPdf($folderId, $filename, $contents);

        return new FiledDocument($this->pathLabel($segments), $filename, $created['id'], $created['webLink']);
    }

    /** Create the nested folders if missing, returning the deepest folder's id. */
    private function ensureFolderPath(array $segments): string
    {
        $parent = $this->rootFolderId ?: 'root';

        foreach ($segments as $name) {
            $parent = $this->client->findFolder($parent, $name)
                ?? $this->client->createFolder($parent, $name);
        }

        return $parent;
    }

    /** "C11_2026-06-20_..._signed.pdf" -> "C11_2026-06-20_..._signed.superseded_20260622_140501.pdf" */
    private function supersededName(string $filename): string
    {
        $base = preg_replace('/\.pdf$/i', '', $filename);

        return $base.'.superseded_'.now()->format('Ymd_His').'.pdf';
    }

    private function pathLabel(array $segments): string
    {
        return implode('/', $segments);
    }
}
