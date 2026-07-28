<?php

namespace App\Services\Drive;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

/**
 * Real DriveClient backed by the Google service account. Thin I/O only — no business logic.
 * All calls pass supportsAllDrives/includeItemsFromAllDrives so the same code works whether the
 * target folder is in My Drive (shared with the SA) or a Shared Drive (SA added as a member).
 */
class GoogleDriveClient implements DriveClient
{
    public function __construct(private string $credentialsPath) {}

    public function findFolder(string $parentId, string $name): ?string
    {
        $escaped = str_replace("'", "\\'", $name);
        $q = "name = '{$escaped}' and mimeType = 'application/vnd.google-apps.folder' "
            ."and '{$parentId}' in parents and trashed = false";

        return $this->firstId($q);
    }

    public function createFolder(string $parentId, string $name): string
    {
        $created = $this->service()->files->create(
            new DriveFile([
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId],
            ]),
            ['fields' => 'id', 'supportsAllDrives' => true]
        );

        return $created->getId();
    }

    public function findFile(string $folderId, string $name): ?string
    {
        $escaped = str_replace("'", "\\'", $name);
        $q = "name = '{$escaped}' and mimeType != 'application/vnd.google-apps.folder' "
            ."and '{$folderId}' in parents and trashed = false";

        return $this->firstId($q);
    }

    public function uploadPdf(string $folderId, string $name, string $contents): array
    {
        $created = $this->service()->files->create(
            new DriveFile(['name' => $name, 'parents' => [$folderId]]),
            [
                'data' => $contents,
                'mimeType' => 'application/pdf',
                'uploadType' => 'multipart',
                'fields' => 'id,webViewLink',
                'supportsAllDrives' => true,
            ]
        );

        return ['id' => $created->getId(), 'webLink' => $created->getWebViewLink()];
    }

    public function uploadFile(string $folderId, string $name, string $contents, string $mimeType): array
    {
        $created = $this->service()->files->create(
            new DriveFile(['name' => $name, 'parents' => [$folderId]]),
            [
                'data' => $contents,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,webViewLink',
                'supportsAllDrives' => true,
            ]
        );

        return ['id' => $created->getId(), 'webLink' => $created->getWebViewLink()];
    }

    public function moveRename(string $fileId, string $toFolderId, string $newName): array
    {
        $service = $this->service();
        $current = $service->files->get($fileId, ['fields' => 'id,parents', 'supportsAllDrives' => true]);

        $service->files->update(
            $fileId,
            new DriveFile(['name' => $newName]),
            [
                'addParents' => $toFolderId,
                'removeParents' => implode(',', $current->getParents() ?? []),
                'fields' => 'id,webViewLink',
                'supportsAllDrives' => true,
            ]
        );
        $updated = $service->files->get($fileId, ['fields' => 'id,webViewLink', 'supportsAllDrives' => true]);

        return ['id' => $updated->getId(), 'webLink' => $updated->getWebViewLink()];
    }

    public function listChildren(string $folderId): array
    {
        $service = $this->service();
        $escaped = str_replace("'", "\\'", $folderId);
        $q = "'{$escaped}' in parents and trashed = false";

        $out = [];
        $pageToken = null;
        do {
            $resp = $service->files->listFiles([
                'q' => $q,
                'fields' => 'nextPageToken, files(id,name,mimeType,webViewLink)',
                'pageSize' => 1000,
                'pageToken' => $pageToken,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);
            foreach ($resp->getFiles() as $f) {
                $out[] = [
                    'id' => $f->getId(),
                    'name' => $f->getName(),
                    'mimeType' => $f->getMimeType(),
                    'webLink' => $f->getWebViewLink(),
                ];
            }
            $pageToken = $resp->getNextPageToken();
        } while ($pageToken);

        return $out;
    }

    /**
     * Run a raw Drive query and return matching files (fully paginated, all drives).
     *
     * @return list<array{id:string, name:string, mimeType:string}>
     */
    public function query(string $q): array
    {
        $service = $this->service();
        $out = [];
        $pageToken = null;
        do {
            $resp = $service->files->listFiles([
                'q' => $q,
                'fields' => 'nextPageToken, files(id,name,mimeType)',
                'pageSize' => 1000,
                'pageToken' => $pageToken,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);
            foreach ($resp->getFiles() as $f) {
                $out[] = ['id' => $f->getId(), 'name' => $f->getName(), 'mimeType' => $f->getMimeType()];
            }
            $pageToken = $resp->getNextPageToken();
        } while ($pageToken);

        return $out;
    }

    /** Move a file/folder to Drive trash (recoverable ~30 days). Trashing a folder trashes its contents. */
    public function trashFile(string $fileId): void
    {
        $this->service()->files->update(
            $fileId,
            new DriveFile(['trashed' => true]),
            ['supportsAllDrives' => true]
        );
    }

    public function downloadFile(string $fileId): string
    {
        $response = $this->service()->files->get(
            $fileId,
            ['alt' => 'media', 'supportsAllDrives' => true]
        );

        return (string) $response->getBody();
    }

    public function describeFolder(string $folderId): ?array
    {
        try {
            $f = $this->service()->files->get($folderId, [
                'fields' => 'id,name,mimeType,driveId',
                'supportsAllDrives' => true,
            ]);
        } catch (\Throwable $e) {
            // 404/403 => the SA can't see it (wrong id or not shared with the SA yet).
            return null;
        }

        return [
            'id' => $f->getId(),
            'name' => $f->getName(),
            'mimeType' => $f->getMimeType(),
            'driveId' => $f->getDriveId(),
        ];
    }

    private function firstId(string $q): ?string
    {
        $found = $this->service()->files->listFiles([
            'q' => $q,
            'fields' => 'files(id)',
            'pageSize' => 1,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return count($found->getFiles()) > 0 ? $found->getFiles()[0]->getId() : null;
    }

    private function service(): Drive
    {
        $client = new Client;
        $client->setAuthConfig($this->credentialsPath);
        $client->addScope(Drive::DRIVE);

        return new Drive($client);
    }
}
