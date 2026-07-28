<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\User;
use App\Services\Drive\DriveClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeDriveClient;
use Tests\TestCase;

class LabAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_create_command_clones_the_register(): void
    {
        $this->artisan('lab:create', ['name' => 'New Tox Lab', '--clia' => '34D9999'])
            ->assertExitCode(0);

        $lab = Lab::where('name', 'New Tox Lab')->first();
        $this->assertNotNull($lab);
        $this->assertSame('34D9999', $lab->clia_number);
        $this->assertSame(17, $lab->obligations()->count());
    }

    public function test_labs_admin_is_super_admin_only(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist'); // lab manager, not super admin
        $this->get(route('labs.index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $this->get(route('labs.index'))->assertOk();
    }

    public function test_super_admin_can_create_lab_via_ui(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        Livewire::test('lab-management')->set('newName', 'UI Lab')->call('createLab');

        $lab = Lab::where('name', 'UI Lab')->first();
        $this->assertNotNull($lab);
        $this->assertSame(17, $lab->obligations()->count());
        $this->assertDatabaseHas('audit_log', ['entity_type' => 'lab', 'action' => 'lab_create']);
    }

    public function test_wizard_step_one_requires_a_name_before_advancing(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        Livewire::test('lab-management')
            ->set('newName', '')
            ->call('nextStep')
            ->assertHasErrors('newName')
            ->assertSet('step', 1)
            ->set('newName', 'Acme Tox')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 2);
    }

    public function test_wizard_verifies_a_shared_drive(): void
    {
        $fake = new FakeDriveClient;
        $fake->putFolder('shared-123', 'Acme — CLIA Compliance', driveId: 'shared-123');
        $fake->putFolder('mydrive-9', 'Just a folder'); // no driveId => My Drive
        $this->app->instance(DriveClient::class, $fake);

        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        // Real Shared Drive => ok
        Livewire::test('lab-management')
            ->set('newDrive', 'shared-123')
            ->call('verifyDrive')
            ->assertSet('driveCheckState', 'ok');

        // A My Drive folder => warn (still connected, but wrong kind)
        Livewire::test('lab-management')
            ->set('newDrive', 'mydrive-9')
            ->call('verifyDrive')
            ->assertSet('driveCheckState', 'warn');

        // Unknown id => fail
        Livewire::test('lab-management')
            ->set('newDrive', 'nope')
            ->call('verifyDrive')
            ->assertSet('driveCheckState', 'fail');
    }

    public function test_super_admin_can_toggle_and_edit_lab(): void
    {
        $lab = $this->makeLab();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        Livewire::test('lab-management')
            ->set("edits.{$lab->id}.drive", 'drive-folder-123')
            ->call('toggleActive', $lab->id);

        $this->assertFalse($lab->fresh()->active);
        $this->assertSame('drive-folder-123', $lab->fresh()->drive_root_folder_id);
    }
}
