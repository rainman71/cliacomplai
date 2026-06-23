<?php

namespace App\Http\Controllers;

use App\Models\FormResponse;
use App\Models\Lab;
use App\Services\FormOverlayRenderer;
use App\Services\FormService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class FormPdfController extends Controller
{
    public function __construct(
        private FormService $forms,
        private FormOverlayRenderer $overlay,
    ) {}

    public function __invoke(Lab $lab, string $response): Response
    {
        $form = FormResponse::forLab($lab->id)->findOrFail($response);
        $filename = "{$form->form_code}-{$lab->id}-{$form->completed_date?->toDateString()}.pdf";

        // Prefer the real official template (coordinate overlay) when one exists for this form;
        // fall back to the dompdf facsimile for forms not yet mapped.
        if ($this->overlay->supports($form->form_code)) {
            return response($this->overlay->render($form), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        $def = $this->forms->definition($form->form_code);

        $pdf = Pdf::loadView($def['pdf_view'], [
            'form' => $form,
            'def' => $def,
            'lab' => $lab,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
