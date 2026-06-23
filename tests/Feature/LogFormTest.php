<?php

namespace Tests\Feature;

use App\Models\FormResponse;
use App\Models\Obligation;
use App\Services\FormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Rightsize-owned LOG forms (repeating data-entry tables) for the numeric/tabular obligations
 * C06/C07/C08/C13/C14 — completing one stores its rows, advances the obligation, and renders a
 * Rightsize-branded PDF natively.
 */
class LogFormTest extends TestCase
{
    use RefreshDatabase;

    /** code => [obligation, date_field, first column key] */
    private const FORMS = [
        'RSL-DOC-100' => ['C06', 'review_date', 'procedure'],
        'RSL-EQ-100' => ['C07', 'log_date', 'instrument'],
        'RSL-EQ-110' => ['C08', 'log_date', 'device'],
        'RSL-HR-110' => ['C13', 'verified_date', 'name'],
        'RSL-QC-110' => ['C14', 'log_date', 'analyte'],
    ];

    public function test_each_log_form_completes_its_obligation_and_stores_rows(): void
    {
        foreach (self::FORMS as $code => [$obligationCode, $dateField, $firstCol]) {
            $lab = $this->makeLab();
            $this->actingInLab($lab, 'compliance_specialist');

            Livewire::test('log-form', ['lab' => $lab, 'code' => $code])
                ->set("values.$dateField", '2026-06-22')
                ->set("rows.0.$firstCol", 'Sample entry')
                ->call('submit')
                ->assertHasNoErrors();

            $resp = FormResponse::forLab($lab->id)->where('form_code', $code)->first();
            $this->assertNotNull($resp, "$code should file a response");
            $this->assertSame('Sample entry', $resp->answers['rows'][0][$firstCol]);

            $o = Obligation::where('code', $obligationCode)->first();
            $this->assertSame('2026-06-22', $o->last_completed->toDateString(), "$code should complete $obligationCode");
            $this->assertSame(1, $o->completions()->count());
        }
    }

    public function test_blank_rows_are_dropped(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        // Default has one blank row; submitting without filling it should file zero rows.
        Livewire::test('log-form', ['lab' => $lab, 'code' => 'RSL-EQ-100'])
            ->set('values.log_date', '2026-06-22')
            ->call('submit')
            ->assertHasNoErrors();

        $resp = FormResponse::forLab($lab->id)->where('form_code', 'RSL-EQ-100')->first();
        $this->assertSame([], $resp->answers['rows']);
    }

    public function test_log_form_pages_render_and_pdf_downloads(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        foreach (array_keys(self::FORMS) as $code) {
            $this->get(route('forms.show', ['lab' => $lab->id, 'code' => $code]))->assertOk();
        }

        $resp = app(FormService::class)->submit('RSL-QC-110', [
            'fields' => ['log_date' => '2026-06-22'],
            'rows' => [['analyte' => 'Fentanyl', 'sample' => 'S1', 'inst_a' => '12', 'inst_b' => '12.4', 'diff' => 'within', 'date' => '2026-06-22']],
        ], '2026-06-22');

        $this->get(route('forms.pdf', ['lab' => $lab->id, 'response' => $resp->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
