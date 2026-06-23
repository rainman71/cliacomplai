<?php

namespace Tests\Feature;

use App\Models\Obligation;
use App\Models\User;
use App\Support\CurrentLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_lab_user_is_redirected_to_portfolio(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist'); // one lab, not super admin

        $this->get(route('executive'))->assertRedirect(route('portfolio'));
    }

    public function test_super_admin_sees_overdue_across_labs(): void
    {
        $labA = $this->makeLab('Lab A');
        $this->makeLab('Lab B');

        app(CurrentLab::class)->set($labA);
        Obligation::where('code', 'C12')->update(['last_completed' => '2026-01-01', 'next_due' => '2026-02-01']);

        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->get(route('executive'))
            ->assertOk()
            ->assertSee('Lab A')
            ->assertSee('Lab B')
            ->assertSee('QC review'); // the overdue C12 in Lab A
    }

    public function test_executive_csv_exports_for_super_admin(): void
    {
        $this->makeLab('Lab A');
        $this->makeLab('Lab B');
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $res = $this->get(route('executive.csv'));

        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
