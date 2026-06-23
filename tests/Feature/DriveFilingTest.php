<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\Obligation;
use App\Services\Drive\DriveFiler;
use App\Services\Drive\DriveNaming;
use App\Services\Drive\FiledDocument;
use App\Services\Drive\NullDriveFiler;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DriveFilingTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lab = $this->makeLab();
    }

    public function test_naming_follows_the_convention(): void
    {
        $naming = new DriveNaming();
        $c02 = Obligation::where('code', 'C02')->first();

        $this->assertSame(['2026 Annual Documents', 'Proficiency Testing'], $naming->folderSegments($c02, '2026-06-15'));
        $this->assertSame('C02_2026-06-15_PT_regulated_analytes_AAB_signed.pdf', $naming->filename($c02, '2026-06-15'));
    }

    public function test_pipette_check_has_its_own_folder(): void
    {
        $naming = new DriveNaming();
        $c08 = Obligation::where('code', 'C08')->first();

        $this->assertSame(['2026 Annual Documents', 'Pipette Checks'], $naming->folderSegments($c08, '2026-06-15'));
    }

    public function test_default_filer_is_the_null_filer(): void
    {
        $this->assertInstanceOf(NullDriveFiler::class, app(DriveFiler::class));
        $this->assertFalse(app(DriveFiler::class)->isConfigured());
    }

    public function test_completion_files_to_drive_and_records_location(): void
    {
        $filed = new FiledDocument(
            folderPath: '2026 Annual Documents/Proficiency Testing',
            filename: 'C02_2026-06-15_PT_regulated_analytes_AAB_signed.pdf',
            fileId: 'drive-file-xyz',
            webLink: 'https://drive.google.com/file/d/drive-file-xyz/view',
        );

        $mock = Mockery::mock(DriveFiler::class);
        $mock->shouldReceive('fileCompletion')->once()->andReturn($filed);
        $this->app->instance(DriveFiler::class, $mock);

        $svc = app(SignatureService::class);
        $c02 = Obligation::where('code', 'C02')->first();
        $req = $svc->sendForSignature($c02);
        foreach ($req->signers as $s) {
            $svc->markSigned($s);
        }

        $completion = $svc->complete($req->fresh('signers'));

        $this->assertSame('drive-file-xyz', $completion->fresh()->drive_file_id);
        $this->assertSame($filed->webLink, $c02->fresh()->document_link);
    }
}
