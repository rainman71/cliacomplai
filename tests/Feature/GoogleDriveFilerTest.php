<?php

namespace Tests\Feature;

use App\Models\Completion;
use App\Models\Obligation;
use App\Services\Drive\DriveNaming;
use App\Services\Drive\GoogleDriveFiler;
use Tests\Support\FakeDriveClient;
use Tests\TestCase;

class GoogleDriveFilerTest extends TestCase
{
    private function filer(FakeDriveClient $client): GoogleDriveFiler
    {
        return new GoogleDriveFiler(new DriveNaming(), $client, 'root');
    }

    private function obligation(): Obligation
    {
        return new Obligation(['code' => 'C11', 'category' => 'Safety', 'name' => 'Lab safety check']);
    }

    public function test_first_filing_creates_one_file_and_no_archive(): void
    {
        $client = new FakeDriveClient();
        $completion = new Completion(['completed_date' => '2026-06-20']);

        $filed = $this->filer($client)->fileGeneratedPdf($this->obligation(), $completion, 'PDF-V1');

        $folder = $client->resolvePath('root', '2026 Annual Documents', 'Safety');
        $this->assertNotNull($folder);
        $files = $client->filesInFolder($folder);
        $this->assertCount(1, $files);
        $this->assertSame('PDF-V1', $files[0]['contents']);
        $this->assertSame('C11_2026-06-20_Lab_safety_check_signed.pdf', $files[0]['name']);
        $this->assertNull($client->findFolder($folder, 'Archived')); // no archive yet
        $this->assertStringStartsWith('fake://drive/', $filed->webLink);
    }

    public function test_refile_archives_previous_version_and_never_overwrites(): void
    {
        $client = new FakeDriveClient();
        $obligation = $this->obligation();
        $completion = new Completion(['completed_date' => '2026-06-20']);

        $this->filer($client)->fileGeneratedPdf($obligation, $completion, 'PDF-V1');
        $this->filer($client)->fileGeneratedPdf($obligation, $completion, 'PDF-V2'); // same name → collision

        $folder = $client->resolvePath('root', '2026 Annual Documents', 'Safety');

        // Current folder holds exactly the new version under the canonical name.
        $current = $client->filesInFolder($folder);
        $this->assertCount(1, $current);
        $this->assertSame('PDF-V2', $current[0]['contents']);
        $this->assertSame('C11_2026-06-20_Lab_safety_check_signed.pdf', $current[0]['name']);

        // The previous version is preserved (not overwritten/deleted) in the Archived subfolder.
        $archive = $client->findFolder($folder, 'Archived');
        $this->assertNotNull($archive);
        $archived = $client->filesInFolder($archive);
        $this->assertCount(1, $archived);
        $this->assertSame('PDF-V1', $archived[0]['contents']);
        $this->assertStringContainsString('.superseded_', $archived[0]['name']);
    }

    public function test_folders_are_reused_not_duplicated(): void
    {
        $client = new FakeDriveClient();

        // File two different obligations/dates that share the "2026 Annual Documents" parent.
        $this->filer($client)->fileGeneratedPdf($this->obligation(), new Completion(['completed_date' => '2026-06-20']), 'A');
        $c02 = new Obligation(['code' => 'C02', 'category' => 'Proficiency Testing', 'name' => 'PT regulated']);
        $this->filer($client)->fileGeneratedPdf($c02, new Completion(['completed_date' => '2026-06-21']), 'B');

        $annualFolders = array_filter($client->folders, fn ($f) => $f['name'] === '2026 Annual Documents' && $f['parent'] === 'root');
        $this->assertCount(1, $annualFolders); // the shared parent folder was reused, not duplicated
    }
}
