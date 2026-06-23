<?php

namespace Tests\Feature;

use App\Models\FormResponse;
use App\Models\Obligation;
use App\Services\FormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_safety_checklist_submission_stores_answers_and_completes_c11(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        Livewire::test('safety-checklist', ['lab' => $lab])
            ->set('reviewDate', '2026-06-21')
            ->set('completedBy', 'Jane Tech')
            ->set('techSupervisor', 'Dr. Foster')
            ->set('items.sink_eyewash.answer', 'no')
            ->set('items.sink_eyewash.note', 'Ordered replacement cartridge')
            ->call('submit')
            ->assertHasNoErrors();

        $resp = FormResponse::forLab($lab->id)->first();
        $this->assertNotNull($resp);
        $this->assertSame('CMP-173', $resp->form_code);
        $this->assertSame('complete', $resp->status);
        $this->assertSame('Jane Tech', $resp->answers['completed_by']);
        $this->assertSame('no', $resp->answers['items']['sink_eyewash']['answer']);

        // Submitting the form should complete C11 and advance its annual schedule.
        $c11 = Obligation::where('code', 'C11')->first();
        $this->assertSame('2026-06-21', $c11->last_completed->toDateString());
        $this->assertSame('2027-06-21', $c11->next_due->toDateString());
        $this->assertSame(1, $c11->completions()->count());
        $this->assertStringContainsString('/pdf', $c11->document_link);
    }

    public function test_form_response_is_lab_scoped(): void
    {
        $labA = $this->makeLab('Lab A');
        $this->actingInLab($labA, 'compliance_specialist');
        app(FormService::class)->submit('CMP-173', ['items' => [], 'completed_by' => 'A', 'tech_supervisor' => 'A', 'review_date' => '2026-06-21'], '2026-06-21');

        $labB = $this->makeLab('Lab B'); // makeLab sets CurrentLab to Lab B
        $this->assertSame(0, FormResponse::count());                 // scoped to Lab B → none
        $this->assertSame(1, FormResponse::forLab($labA->id)->count());
    }

    public function test_completed_form_pdf_downloads(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        $resp = app(FormService::class)->submit('CMP-173', [
            'items' => ['sink_eyewash' => ['answer' => 'no', 'note' => 'fixed']],
            'completed_by' => 'Jane', 'tech_supervisor' => 'Foster', 'review_date' => '2026-06-21',
        ], '2026-06-21');

        $this->get(route('forms.pdf', ['lab' => $lab->id, 'response' => $resp->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_cms209_prefills_personnel_and_completes_c05(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'lab_director'); // a manager; also becomes an active member

        Livewire::test('cms-209-report', ['lab' => $lab])
            ->set('people.0.qualifications', 'PhD, HCLD(ABB)')
            ->set('preparedBy', 'Dr. Foster')
            ->call('submit')
            ->assertHasNoErrors();

        $resp = FormResponse::forLab($lab->id)->where('form_code', 'CMS-209')->first();
        $this->assertNotNull($resp);
        $this->assertNotEmpty($resp->answers['people']);                       // pre-filled from membership
        $this->assertSame('PhD, HCLD(ABB)', $resp->answers['people'][0]['qualifications']);

        $c05 = Obligation::where('code', 'C05')->first();
        $this->assertSame(1, $c05->completions()->count());                    // completing CMS-209 advances C05
    }

    public function test_both_form_wizard_pages_render(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        $this->get(route('forms.safety-checklist', $lab->id))->assertOk();
        $this->get(route('forms.cms-209', $lab->id))->assertOk();
        $this->get(route('forms.reference-lab-approval', $lab->id))->assertOk();
    }

    public function test_reference_lab_approval_completes_c10(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        Livewire::test('reference-lab-approval', ['lab' => $lab])
            ->set('approver', 'Dr. Foster')
            ->set('labs.0.name', 'Select Laboratory Partners')
            ->set('labs.0.clia_number', '34D1234567')
            ->set('labs.0.tests', 'Confirmatory LCMS')
            ->call('submit')
            ->assertHasNoErrors();

        $resp = FormResponse::forLab($lab->id)->where('form_code', 'CMP-172')->first();
        $this->assertNotNull($resp);
        $this->assertSame('Select Laboratory Partners', $resp->answers['reference_labs'][0]['name']);

        $c10 = Obligation::where('code', 'C10')->first();
        $this->assertSame(1, $c10->completions()->count());
    }

    public function test_reference_lab_approval_drops_blank_rows(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        // Default has one blank row; submitting without filling a name should file zero labs.
        Livewire::test('reference-lab-approval', ['lab' => $lab])
            ->set('approver', 'Dr. Foster')
            ->call('submit')
            ->assertHasNoErrors();

        $resp = FormResponse::forLab($lab->id)->where('form_code', 'CMP-172')->first();
        $this->assertSame([], $resp->answers['reference_labs']);
    }

    public function test_generic_checklist_form_completes_c04(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        Livewire::test('form-wizard', ['lab' => $lab, 'code' => 'CMP-132'])
            ->set('values.visit_date', '2026-06-21')
            ->set('values.compliance_member', 'Cindy Dry')
            ->set('items.docs_complete.answer', 'no')
            ->set('items.docs_complete.note', 'PT packet missing')
            ->call('submit')
            ->assertHasNoErrors();

        $resp = FormResponse::forLab($lab->id)->where('form_code', 'CMP-132')->first();
        $this->assertNotNull($resp);
        $this->assertSame('Cindy Dry', $resp->answers['fields']['compliance_member']);
        $this->assertSame('no', $resp->answers['items']['docs_complete']['answer']);

        $c04 = Obligation::where('code', 'C04')->first();
        $this->assertSame('2026-06-21', $c04->last_completed->toDateString());
        $this->assertSame('2026-12-21', $c04->next_due->toDateString()); // +6 months
        $this->assertSame(1, $c04->completions()->count());
    }

    public function test_generic_field_form_completes_c12(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        Livewire::test('form-wizard', ['lab' => $lab, 'code' => 'CMP-130'])
            ->set('values.review_date', '2026-06-21')
            ->set('values.area_of_review', 'IR-500')
            ->set('values.corrective_action', 'None required')
            ->call('submit')
            ->assertHasNoErrors();

        $resp = FormResponse::forLab($lab->id)->where('form_code', 'CMP-130')->first();
        $this->assertSame('IR-500', $resp->answers['fields']['area_of_review']);

        $c12 = Obligation::where('code', 'C12')->first();
        $this->assertSame('2026-06-21', $c12->last_completed->toDateString());
        $this->assertSame(1, $c12->completions()->count());
    }

    public function test_event_driven_form_completes_c15_without_next_due(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        Livewire::test('form-wizard', ['lab' => $lab, 'code' => 'CMP-171'])
            ->set('values.date_submitted', '2026-06-21')
            ->set('values.test_name', 'Fentanyl confirmation')
            ->call('submit')
            ->assertHasNoErrors();

        $c15 = Obligation::where('code', 'C15')->first();
        $this->assertSame('2026-06-21', $c15->last_completed->toDateString());
        $this->assertNull($c15->next_due); // event-driven: null interval → no recurring due date
        $this->assertSame(1, $c15->completions()->count());
    }

    public function test_all_generic_form_pages_render(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        foreach (['CMP-132', 'CMP-130', 'CMP-131', 'CMP-171', 'RSL-QC-120'] as $code) {
            $this->get(route('forms.show', ['lab' => $lab->id, 'code' => $code]))->assertOk();
        }
    }

    public function test_iqcp_review_form_completes_c17(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        Livewire::test('form-wizard', ['lab' => $lab, 'code' => 'RSL-QC-120'])
            ->set('values.review_date', '2026-06-23')
            ->set('values.test_systems', 'IR-500 immunoassay screen')
            ->call('submit')
            ->assertHasNoErrors();

        $c17 = Obligation::where('code', 'C17')->first();
        $this->assertSame('2026-06-23', $c17->last_completed->toDateString());
        $this->assertSame('2027-06-23', $c17->next_due->toDateString()); // annual
        $this->assertSame(1, $c17->completions()->count());
    }

    public function test_remaining_generic_forms_complete_their_obligations(): void
    {
        $map = [
            'CMS-116' => ['C01', 'effective_date'],
            'CMP-150' => ['C02', 'survey_date'],
            'CMP-190' => ['C03', 'attestation_date'],
            'CMP-133' => ['C09', 'review_date'],
        ];

        foreach ($map as $code => [$obligationCode, $dateField]) {
            $lab = $this->makeLab();
            $this->actingInLab($lab, 'compliance_specialist');

            Livewire::test('form-wizard', ['lab' => $lab, 'code' => $code])
                ->set("values.$dateField", '2026-06-21')
                ->call('submit')
                ->assertHasNoErrors();

            $o = Obligation::where('code', $obligationCode)->first();
            $this->assertSame('2026-06-21', $o->last_completed->toDateString(), "$code should complete $obligationCode");
            $this->assertSame(1, $o->completions()->count(), "$code should record one completion");
            $this->assertSame(1, FormResponse::forLab($lab->id)->where('form_code', $code)->count());
        }
    }

    public function test_remaining_generic_form_pages_render(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        foreach (['CMS-116', 'CMP-150', 'CMP-190', 'CMP-133'] as $code) {
            $this->get(route('forms.show', ['lab' => $lab->id, 'code' => $code]))->assertOk();
        }
    }

    public function test_configured_drive_filer_uploads_generated_pdf_and_links_it(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        // Swap in a "configured" Drive filer that pretends to upload and returns a Drive link.
        $stub = new class(app(\App\Services\Drive\DriveNaming::class)) extends \App\Services\Drive\NullDriveFiler {
            public function isConfigured(): bool
            {
                return true;
            }

            public function fileGeneratedPdf(\App\Models\Obligation $obligation, \App\Models\Completion $completion, string $contents, ?string $fallbackLink = null): \App\Services\Drive\FiledDocument
            {
                // The PDF was actually rendered before this call (contents is non-empty).
                \PHPUnit\Framework\Assert::assertNotEmpty($contents);

                return new \App\Services\Drive\FiledDocument(
                    '2026 Annual Documents/Safety',
                    'C11.pdf',
                    'drive-file-123',
                    'https://drive.google.com/file/d/drive-file-123/view',
                );
            }
        };
        $this->app->instance(\App\Services\Drive\DriveFiler::class, $stub);

        app(FormService::class)->submit('CMP-173', [
            'items' => [], 'completed_by' => 'Jane', 'tech_supervisor' => 'Foster', 'review_date' => '2026-06-21',
        ], '2026-06-21');

        $c11 = Obligation::where('code', 'C11')->first();
        $this->assertSame('https://drive.google.com/file/d/drive-file-123/view', $c11->document_link);

        $resp = FormResponse::forLab($lab->id)->where('form_code', 'CMP-173')->first();
        $this->assertSame('drive-file-123', $resp->drive_file_id);
        $this->assertSame('https://drive.google.com/file/d/drive-file-123/view', $resp->document_link);
    }
}
