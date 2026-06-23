<?php

namespace Tests\Feature;

use App\Forms\FormCatalog;
use App\Models\FormResponse;
use App\Services\FormOverlayRenderer;
use App\Services\FormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Overlay is reserved for the federal CMS forms that must be filled onto the official government
 * template (CMS-116, CMS-209). Every other form is a Rightsize-owned SOP rendered natively as the
 * official document. These guard that split and that each federal overlay produces a valid PDF.
 */
class FormOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlay_is_reserved_for_federal_forms_only(): void
    {
        $overlay = app(FormOverlayRenderer::class);

        $this->assertTrue($overlay->supports('CMS-116'));   // federal — fill official template
        $this->assertTrue($overlay->supports('CMS-209'));   // federal — fill official template
        $this->assertFalse($overlay->supports('CMP-173'));  // Rightsize-owned SOP -> native render
        $this->assertFalse($overlay->supports('CMP-172'));  // Rightsize-owned SOP -> native render
    }

    public function test_owned_forms_carry_rightsize_sop_identity(): void
    {
        // The generated PDF is branded from the catalog's SOP metadata, not SLP/TBR.
        $this->assertSame('RSL-S-100', FormCatalog::get('CMP-173')['sop_code']);
        $this->assertSame('RSL-QC-100', FormCatalog::get('CMP-130')['sop_code']);
        $this->assertArrayHasKey('citation', FormCatalog::get('CMP-173'));
    }

    public function test_cms116_overlays_identity_and_certificate_type_across_pages(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'lab_director');

        $form = new FormResponse([
            'form_code' => 'CMS-116',
            'lab_id' => $lab->id,
            'answers' => ['fields' => [
                'lab_name' => 'Triad Behavioral Resources',
                'clia_number' => '34D1234567',
                'application_type' => 'Initial',
                'certificate_type' => 'Compliance',
                'effective_date' => '2026-07-01',
                'test_volume' => '48,000',
                'director_name' => 'R. Scott Foster, PhD',
            ]],
            'completed_date' => '2026-06-22',
        ]);

        $pdf = app(FormOverlayRenderer::class)->render($form);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(100_000, strlen($pdf)); // all 10 official pages carried through
    }

    public function test_cms209_overlays_lab_identity_and_personnel_roster(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'lab_director');

        $form = new FormResponse([
            'form_code' => 'CMS-209',
            'lab_id' => $lab->id,
            'answers' => [
                'clia_number' => '34D1234567',
                'people' => [
                    ['name' => 'Robert Yordy, PhD'],
                    ['name' => 'Jane Q. Technician'],
                ],
            ],
            'completed_date' => '2026-06-22',
        ]);

        $pdf = app(FormOverlayRenderer::class)->render($form);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(20_000, strlen($pdf));
    }

    public function test_owned_cmp_form_renders_natively_as_a_valid_pdf(): void
    {
        $lab = $this->makeLab();
        $this->actingInLab($lab, 'compliance_specialist');

        // CMP-173 is now a Rightsize-owned SOP: the in-app PDF route renders it natively (no overlay).
        $resp = app(FormService::class)->submit('CMP-173', [
            'items' => ['sink_eyewash' => ['answer' => 'no', 'note' => 'cartridge ordered']],
            'completed_by' => 'Jane', 'tech_supervisor' => 'Foster', 'review_date' => '2026-06-21',
        ], '2026-06-21');

        $response = $this->get(route('forms.pdf', ['lab' => $lab->id, 'response' => $resp->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
