<?php

namespace Tests\Feature;

use App\Models\LabUser;
use App\Models\Obligation;
use App\Models\User;
use App\Support\CurrentLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_page_requires_manager_role(): void
    {
        $lab = $this->makeLab();

        $this->actingInLab($lab, 'tech_staff');
        $this->get(route('users', $lab))->assertForbidden();

        $this->actingInLab($lab, 'compliance_specialist');
        $this->get(route('users', $lab))->assertOk();
    }

    public function test_member_of_one_lab_cannot_access_another(): void
    {
        $labA = $this->makeLab('Lab A');
        $labB = $this->makeLab('Lab B');
        $this->actingInLab($labA, 'compliance_specialist'); // member of A only

        $this->get(route('dashboard', $labA))->assertOk();
        $this->get(route('dashboard', $labB))->assertForbidden();
        $this->get(route('users', $labB))->assertForbidden();
    }

    public function test_super_admin_can_access_any_lab(): void
    {
        $lab = $this->makeLab();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->get(route('dashboard', $lab))->assertOk();
        $this->get(route('users', $lab))->assertOk();
    }

    public function test_manager_can_assign_multiple_roles(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');
        $target = User::factory()->create();
        $m = LabUser::create(['lab_id' => $lab->id, 'user_id' => $target->id, 'active' => true]);
        $m->syncRoles(['tech_staff']);

        Livewire::test('user-management', ['lab' => $lab])
            ->call('toggleRole', $m->id, 'tech_supervisor');

        $this->assertEqualsCanonicalizing(['tech_staff', 'tech_supervisor'], $m->fresh()->roleNames());
        $this->assertDatabaseHas('audit_log', [
            'lab_id' => $lab->id, 'entity_type' => 'lab_user', 'action' => 'lab_role_change',
        ]);
    }

    public function test_manager_can_revoke_access(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');
        $target = User::factory()->create();
        $m = LabUser::create(['lab_id' => $lab->id, 'user_id' => $target->id, 'active' => true]);
        $m->syncRoles(['tech_staff']);

        Livewire::test('user-management', ['lab' => $lab])->call('toggleActive', $m->id);

        $this->assertFalse($m->fresh()->active);
    }

    public function test_manager_cannot_remove_own_management_access(): void
    {
        $lab = $this->makeLab();
        $mgr = $this->actingInLab($lab, 'compliance_specialist');
        $m = $mgr->membershipFor($lab);

        Livewire::test('user-management', ['lab' => $lab])
            ->call('toggleRole', $m->id, 'compliance_specialist'); // try to drop own only manager role

        $this->assertContains('compliance_specialist', $m->fresh()->roleNames());
    }

    public function test_read_only_user_cannot_edit_register(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'tech_staff');
        $c03 = Obligation::where('code', 'C03')->first();

        Livewire::test('compliance-dashboard', ['lab' => $lab])
            ->set("form.{$c03->id}.notes", 'should not save');

        $this->assertNull($c03->fresh()->notes);
    }

    public function test_obligations_are_isolated_per_lab(): void
    {
        $labA = $this->makeLab('Lab A');
        $labB = $this->makeLab('Lab B');
        $current = app(CurrentLab::class);

        $current->set($labA);
        $this->assertSame(17, Obligation::count());

        $current->set($labB);
        $this->assertSame(17, Obligation::count());

        $this->assertSame(34, Obligation::allLabs()->count());
    }
}
