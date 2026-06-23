<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Services\Drive\GoogleDriveScanner;
use Tests\Support\FakeDriveClient;
use Tests\TestCase;

class GoogleDriveScannerTest extends TestCase
{
    public function test_scans_lab_subtree_recursively_and_only_signed_pdfs(): void
    {
        $client = new FakeDriveClient();

        // The lab's tree.
        $root = $client->createFolder('drive-root', 'TBR Compliance');
        $safety = $client->createFolder($root, 'Safety');
        $archived = $client->createFolder($safety, 'Archived');         // nested 2 levels deep
        $qc = $client->createFolder($root, 'QC');
        $client->uploadPdf($safety, 'CMP-173_signed_2026.01.10.pdf', 'a');
        $client->uploadPdf($safety, 'reference_notes.pdf', 'b');         // not signed → excluded
        $client->uploadPdf($archived, 'CMP-173_signed_2025.12.01.pdf', 'c'); // deep → included
        $client->uploadPdf($qc, 'CMP-130_signed_2026.02.20.pdf', 'd');

        // A signed file in a sibling tree OUTSIDE the lab root → must NOT be picked up.
        $other = $client->createFolder('drive-root', 'Other Lab Tree');
        $client->uploadPdf($other, 'CMP-150_signed_2026.03.03.pdf', 'e');

        $lab = new Lab();
        $lab->drive_root_folder_id = $root;

        $names = array_map(fn ($f) => $f->name, (new GoogleDriveScanner($client))->scan($lab));
        sort($names);

        $this->assertSame([
            'CMP-130_signed_2026.02.20.pdf',
            'CMP-173_signed_2025.12.01.pdf', // found nested under Safety/Archived
            'CMP-173_signed_2026.01.10.pdf',
        ], $names);
    }

    public function test_lab_without_a_root_scans_nothing(): void
    {
        $client = new FakeDriveClient();
        $client->uploadPdf($client->createFolder('drive-root', 'X'), 'CMP-173_signed_2026.01.01.pdf', 'a');

        $lab = new Lab(); // no drive_root_folder_id
        $this->assertSame([], (new GoogleDriveScanner($client))->scan($lab));
    }
}
