<?php

namespace App\Services;

use App\Forms\FormCatalog;
use App\Models\AuditLog;
use App\Models\Completion;
use App\Models\FormResponse;
use App\Models\Lab;
use App\Models\Obligation;
use App\Services\Drive\DriveFiler;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persists a completed official form (the wizard's answers) and, when the form is tied to an
 * obligation, records a completion + advances the schedule — the same finalize step the
 * SignatureService performs, so the form itself becomes the dated evidence. The generated PDF
 * is then filed to Drive (best-effort) when a real Drive filer is configured.
 */
class FormService
{
    public function __construct(
        private ComplianceStatusService $status,
        private DriveFiler $drive,
        private FormOverlayRenderer $overlay,
    ) {}

    public function definition(string $code): array
    {
        return FormCatalog::get($code);
    }

    /**
     * @param  array  $answers  the wizard's captured values (shape depends on the form)
     */
    public function submit(string $code, array $answers, ?string $completedDate = null): FormResponse
    {
        $def = $this->definition($code);

        /** @var array{0: FormResponse, 1: ?Completion, 2: ?Obligation} $result */
        $result = DB::transaction(function () use ($def, $code, $answers, $completedDate) {
            $date = $completedDate ?: CarbonImmutable::now()->toDateString();
            $obligation = Obligation::where('code', $def['obligation_code'] ?? '')->first();

            $response = FormResponse::create([
                'obligation_id' => $obligation?->id,
                'form_code' => $code,
                'title' => $def['title'],
                'answers' => $answers,
                'status' => 'complete',
                'completed_date' => $date,
                'completed_by' => auth()->id(),
            ]);

            $completion = null;
            if ($obligation) {
                $pdfUrl = route('forms.pdf', ['lab' => $obligation->lab_id, 'response' => $response->id]);

                $completion = $obligation->completions()->create([
                    'completed_date' => $date,
                    'document_link' => $pdfUrl,
                    'created_by' => auth()->id(),
                ]);

                $nextDue = $this->status->nextDue(CarbonImmutable::parse($date), $obligation->interval_months);
                $oldLast = optional($obligation->last_completed)->toDateString();
                $obligation->update([
                    'last_completed' => $date,
                    'next_due' => $nextDue?->toDateString(),
                    'signature_status' => 'complete',
                    'document_link' => $pdfUrl,
                ]);
                $response->update(['document_link' => $pdfUrl]);

                $this->audit($obligation->id, 'last_completed', $oldLast, $date, 'form_complete');
            }

            return [$response->fresh(), $completion, $obligation];
        });

        [$response, $completion, $obligation] = $result;

        if ($obligation && $completion) {
            $this->fileToDrive($response, $completion, $obligation, $def);
        }

        return $response->fresh();
    }

    /**
     * Render the completed form as a PDF and file it into the lab's Drive tree. Best-effort and
     * only when a real Drive filer is configured — otherwise the in-app PDF route stays the link.
     * A Drive failure must never roll back a completed submission.
     */
    private function fileToDrive(FormResponse $response, Completion $completion, Obligation $obligation, array $def): void
    {
        if (! $this->drive->isConfigured()) {
            return;
        }

        try {
            $lab = Lab::find($obligation->lab_id);
            $contents = $this->renderPdf($response, $def, $lab);

            $filed = $this->drive->fileGeneratedPdf($obligation, $completion, $contents, $response->document_link);

            if ($filed->fileId !== null || ($filed->webLink !== null && $filed->webLink !== $response->document_link)) {
                $link = $filed->webLink ?? $response->document_link;
                $completion->update(['drive_file_id' => $filed->fileId, 'document_link' => $link]);
                $obligation->update(['document_link' => $link]);
                $response->update(['document_link' => $link, 'drive_file_id' => $filed->fileId]);
            }
        } catch (\Throwable $e) {
            Log::warning('[Drive] filing generated form PDF failed; keeping in-app PDF link', [
                'form' => $response->form_code,
                'obligation' => $obligation->code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Render the completed form to PDF bytes — the real official template (coordinate overlay)
     * when one exists for this form, else the dompdf facsimile. Single source of truth shared by
     * Drive filing here and the in-app PDF route (FormPdfController).
     */
    private function renderPdf(FormResponse $response, array $def, ?Lab $lab): string
    {
        if ($this->overlay->supports($response->form_code)) {
            return $this->overlay->render($response);
        }

        return Pdf::loadView($def['pdf_view'], [
            'form' => $response,
            'def' => $def,
            'lab' => $lab,
        ])->output();
    }

    private function audit(int $obligationId, string $field, ?string $old, ?string $new, string $action): void
    {
        AuditLog::create([
            'entity_type' => 'obligation',
            'entity_id' => $obligationId,
            'field' => $field,
            'old_value' => (string) $old,
            'new_value' => (string) $new,
            'action' => $action,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }
}
