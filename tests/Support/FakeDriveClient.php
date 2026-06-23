<?php

namespace Tests\Support;

use App\Services\Drive\DriveClient;

/** In-memory DriveClient for testing filing/archiving logic without touching Google. */
class FakeDriveClient implements DriveClient
{
    /** @var array<string, array{parent: string, name: string}> */
    public array $folders = [];

    /** @var array<string, array{parent: string, name: string, contents: string}> */
    public array $files = [];

    private int $seq = 0;

    public function findFolder(string $parentId, string $name): ?string
    {
        foreach ($this->folders as $id => $f) {
            if ($f['parent'] === $parentId && $f['name'] === $name) {
                return $id;
            }
        }

        return null;
    }

    public function createFolder(string $parentId, string $name): string
    {
        $id = 'folder-'.(++$this->seq);
        $this->folders[$id] = ['parent' => $parentId, 'name' => $name];

        return $id;
    }

    public function findFile(string $folderId, string $name): ?string
    {
        foreach ($this->files as $id => $f) {
            if ($f['parent'] === $folderId && $f['name'] === $name) {
                return $id;
            }
        }

        return null;
    }

    public function uploadPdf(string $folderId, string $name, string $contents): array
    {
        $id = 'file-'.(++$this->seq);
        $this->files[$id] = ['parent' => $folderId, 'name' => $name, 'contents' => $contents];

        return ['id' => $id, 'webLink' => "fake://drive/{$id}"];
    }

    public function moveRename(string $fileId, string $toFolderId, string $newName): array
    {
        $this->files[$fileId]['parent'] = $toFolderId;
        $this->files[$fileId]['name'] = $newName;

        return ['id' => $fileId, 'webLink' => "fake://drive/{$fileId}"];
    }

    public function listChildren(string $folderId): array
    {
        $out = [];
        foreach ($this->folders as $id => $f) {
            if ($f['parent'] === $folderId) {
                $out[] = ['id' => $id, 'name' => $f['name'], 'mimeType' => 'application/vnd.google-apps.folder', 'webLink' => "fake://drive/{$id}"];
            }
        }
        foreach ($this->files as $id => $f) {
            if ($f['parent'] === $folderId) {
                $out[] = ['id' => $id, 'name' => $f['name'], 'mimeType' => 'application/pdf', 'webLink' => "fake://drive/{$id}"];
            }
        }

        return $out;
    }

    public function downloadFile(string $fileId): string
    {
        return $this->files[$fileId]['contents'] ?? '';
    }

    // --- test helpers ---

    /** @return list<array{id: string, name: string, contents: string}> */
    public function filesInFolder(string $folderId): array
    {
        $out = [];
        foreach ($this->files as $id => $f) {
            if ($f['parent'] === $folderId) {
                $out[] = ['id' => $id, 'name' => $f['name'], 'contents' => $f['contents']];
            }
        }

        return $out;
    }

    /** Resolve a folder path from a root, returning the deepest folder id or null. */
    public function resolvePath(string $rootId, string ...$segments): ?string
    {
        $parent = $rootId;
        foreach ($segments as $name) {
            $parent = $this->findFolder($parent, $name);
            if ($parent === null) {
                return null;
            }
        }

        return $parent;
    }
}
