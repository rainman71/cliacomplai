<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
