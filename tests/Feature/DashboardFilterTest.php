<?php

namespace Tests\Feature;

use App\Models\Lab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the shared frequency filter + sort on the dashboard obligation tables. Uses the seeded
 * 17-obligation template, whose interval_months values are known (C01=24, C09/C12=1, C13/C15=null).
 */
class DashboardFilterTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lab = $this->makeLab();
        $this->actingInLab($this->lab, 'compliance_specialist');
    }

    public function test_frequency_filter_narrows_the_register_to_one_cadence(): void
    {
        $test = Livewire::test('compliance-dashboard', ['lab' => $this->lab]);

        // Biennial (24 mo) => only C01, the CLIA certificate renewal.
        $test->set('frequencyFilter', '24');
        $rows = $test->instance()->registerRows;
        $this->assertCount(1, $rows);
        $this->assertSame('C01', $rows->first()['o']->code);

        // Monthly (1 mo) => C09 + C12.
        $test->set('frequencyFilter', '1');
        $codes = $test->instance()->registerRows->map(fn ($r) => $r['o']->code)->sort()->values()->all();
        $this->assertSame(['C09', 'C12'], $codes);

        // Event-driven (null interval) => C13 + C15.
        $test->set('frequencyFilter', 'event');
        $codes = $test->instance()->registerRows->map(fn ($r) => $r['o']->code)->sort()->values()->all();
        $this->assertSame(['C13', 'C15'], $codes);
    }

    public function test_clearing_the_filter_shows_the_whole_register(): void
    {
        $test = Livewire::test('compliance-dashboard', ['lab' => $this->lab]);
        $total = $test->instance()->rows->count();

        $test->set('frequencyFilter', '24');
        $this->assertCount(1, $test->instance()->registerRows);

        $test->set('frequencyFilter', '');
        $this->assertCount($total, $test->instance()->registerRows);
    }

    public function test_sort_by_code_orders_rows_ascending(): void
    {
        $test = Livewire::test('compliance-dashboard', ['lab' => $this->lab])
            ->set('sortBy', 'code');

        $codes = $test->instance()->registerRows->map(fn ($r) => $r['o']->code)->all();
        $sorted = $codes;
        sort($sorted);

        $this->assertSame($sorted, $codes);
        $this->assertSame('C01', $codes[0]);
    }

    public function test_frequency_options_are_labelled_and_ordered_by_cadence(): void
    {
        $options = Livewire::test('compliance-dashboard', ['lab' => $this->lab])
            ->instance()->frequencyOptions;

        $this->assertSame('Monthly', $options['1']);
        $this->assertSame('Annual', $options['12']);
        $this->assertSame('Biennial', $options['24']);
        $this->assertSame('Event-driven', $options['event']);

        // Event-driven (null cadence) always sorts last.
        $this->assertSame('event', array_key_last($options));
    }

    public function test_completeness_tab_honours_the_frequency_filter(): void
    {
        $test = Livewire::test('compliance-dashboard', ['lab' => $this->lab])
            ->set('frequencyFilter', '24');

        $rows = $test->instance()->completenessRowsFiltered;
        $this->assertCount(1, $rows);
        $this->assertSame('C01', $rows->first()['code']);
    }
}
