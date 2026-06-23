<?php

namespace Tests\Feature;

use App\Models\Obligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_worklist_merges_overdue_across_all_labs_for_super_admin(): void
    {
        $labA = $this->makeLab('Lab A'); // makeLab sets CurrentLab to this lab
        Obligation::where('code', 'C12')->update(['last_completed' => '2020-01-01']);

        $labB = $this->makeLab('Lab B');
        Obligation::where('code', 'C09')->update(['last_completed' => '2020-01-01']);

        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->get(route('worklist'))
            ->assertOk()
            ->assertSee('Lab A')
            ->assertSee('Lab B')
            ->assertSee('C12')
            ->assertSee('C09')
            ->assertSee('days');
    }

    public function test_single_lab_non_super_user_is_redirected_to_portfolio(): void
    {
        $lab = $this->makeLab('Solo Lab');
        $this->actingInLab($lab, 'compliance_specialist'); // member of one lab only

        $this->get(route('worklist'))->assertRedirect(route('portfolio'));
    }
}
