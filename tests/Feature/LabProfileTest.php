<?php

namespace Tests\Feature;

use App\Models\Lab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LabProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_save_the_lab_profile(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'lab_director');

        Livewire::test('lab-profile', ['lab' => $lab])
            ->set('cliaNumber', '34D1234567')
            ->set('address', '1050 Revolution Mill Dr')
            ->set('profile.director_name', 'Dr. R. Scott Foster')
            ->set('profile.tech_supervisor', 'U. Koshelap')
            ->call('save')
            ->assertHasNoErrors();

        $lab->refresh();
        $this->assertSame('34D1234567', $lab->clia_number);
        $this->assertSame('1050 Revolution Mill Dr', $lab->address);
        $this->assertSame('Dr. R. Scott Foster', $lab->profileValue('director_name'));
        $this->assertSame('U. Koshelap', $lab->profileValue('tech_supervisor'));
    }

    public function test_read_only_user_cannot_open_the_profile_page(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'tech_staff'); // member but not a manager

        $this->get(route('lab.profile', $lab->id))->assertForbidden();
    }

    public function test_forms_autofill_from_the_lab_profile(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');
        $lab->update(['profile' => [
            'director_name' => 'Dr. R. Scott Foster',
            'hours' => 'Mon–Fri 8–5',
            'certificate_type' => 'Compliance',
        ]]);

        // Generic engine (CMS-116) pulls director/hours/certificate from the profile.
        Livewire::test('form-wizard', ['lab' => $lab, 'code' => 'CMS-116'])
            ->assertSet('values.director_name', 'Dr. R. Scott Foster')
            ->assertSet('values.hours', 'Mon–Fri 8–5')
            ->assertSet('values.certificate_type', 'Compliance');

        // Bespoke reference-lab form pre-fills the approver from the profile director.
        Livewire::test('reference-lab-approval', ['lab' => $lab])
            ->assertSet('approver', 'Dr. R. Scott Foster');

        // Bespoke safety checklist pre-fills the technical supervisor (empty here → blank, no error).
        Livewire::test('safety-checklist', ['lab' => $lab])
            ->assertSet('techSupervisor', '');
    }
}
