<?php

namespace App\Services;

use App\Models\FormResponse;
use App\Models\Lab;
use Illuminate\Support\Arr;
use setasign\Fpdi\Fpdi;

/**
 * Renders a completed form by overlaying its captured answers onto the REAL official template PDF
 * (coordinate stamping via FPDI), rather than building a dompdf facsimile. This produces the
 * actual CMP-###/CMS-### document with the lab's answers typed onto the original blanks/checkboxes.
 *
 * Overlay is reserved for the federal CMS forms that MUST be submitted on the official government
 * document (CMS-116, CMS-209). Every other form is a Rightsize-owned SOP and is rendered natively
 * (the Rightsize-branded Blade), so we no longer overlay any third-party/proprietary template.
 * A form is overlayable when it is a federal form AND its normalized template
 * (resources/form-templates/{CODE}.pdf) and coordinate map (app/Forms/Overlays/{CODE}.php) exist.
 */
class FormOverlayRenderer
{
    /** Federal forms that must be filled onto the official government template. */
    public const OFFICIAL_FEDERAL_FORMS = ['CMS-116', 'CMS-209'];

    /** Whether the given form code is filled by overlaying its official federal template. */
    public function supports(string $code): bool
    {
        return in_array($code, self::OFFICIAL_FEDERAL_FORMS, true)
            && is_file($this->templatePath($code))
            && is_file($this->mapPath($code));
    }

    /**
     * Render $form onto its official template and return the raw PDF bytes.
     *
     * @throws \RuntimeException if the form is not overlayable
     */
    public function render(FormResponse $form): string
    {
        $code = $form->form_code;
        if (! $this->supports($code)) {
            throw new \RuntimeException("No overlay template/map for form {$code}");
        }

        $map = require $this->mapPath($code);
        $context = $this->context($form);

        $pdf = new Fpdi('P', 'pt', [612, 792]);
        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile($this->templatePath($code));
        // Render every template page; stamp each map element on its target page (default page 1).
        // Pages with nothing mapped pass through untouched.
        for ($page = 1; $page <= $pageCount; $page++) {
            $tpl = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
            $pdf->SetTextColor(0, 0, 0);

            $this->stampText($pdf, $map['text'] ?? [], $context, $page);
            $this->stampChecks($pdf, $map['checks'] ?? [], $context, $page);
            if ($page === 1) {
                $this->stampChecklist($pdf, $map['checklist'] ?? null, $context);
                $this->stampRoster($pdf, $map['roster'] ?? null, $context);
            }
        }

        return $pdf->Output('S');
    }

    /**
     * Free-text fields. Each entry stamps its resolved value at (x, baseline-y); `page` (1-based,
     * default 1) selects the template page.
     *
     * @param  list<array{key:string,x:float,y:float,size?:float,page?:int}>  $fields
     */
    private function stampText(Fpdi $pdf, array $fields, array $context, int $page): void
    {
        foreach ($fields as $field) {
            if (($field['page'] ?? 1) !== $page) {
                continue;
            }
            $value = (string) (data_get($context, $field['key']) ?? '');
            if ($value === '') {
                continue;
            }
            $pdf->SetFont('Helvetica', '', $field['size'] ?? 10);
            $pdf->Text($field['x'], $field['y'], $this->ascii($value));
        }
    }

    /**
     * Value-driven checkboxes (e.g. CMS-116 application/certificate type). Stamps an X at (x, y)
     * when the answer at `key` equals `equals`. `page` (1-based, default 1) selects the page.
     *
     * @param  list<array{key:string,equals:string,x:float,y:float,size?:float,page?:int}>  $checks
     */
    private function stampChecks(Fpdi $pdf, array $checks, array $context, int $page): void
    {
        foreach ($checks as $c) {
            if (($c['page'] ?? 1) !== $page) {
                continue;
            }
            if ((string) (data_get($context, $c['key']) ?? '') !== $c['equals']) {
                continue;
            }
            $size = $c['size'] ?? 9;
            $pdf->SetFont('Helvetica', 'B', $size);
            $pdf->Text($c['x'] - ($pdf->GetStringWidth('X') / 2), $c['y'], 'X');
        }
    }

    /**
     * @param  array{yes_x:float,no_x:float,note_x:float,mark:string,size:float,items:array<string,float>}|null  $checklist
     */
    private function stampChecklist(Fpdi $pdf, ?array $checklist, array $context): void
    {
        if (! $checklist) {
            return;
        }

        $mark = $checklist['mark'] ?? 'X';
        $size = $checklist['size'] ?? 11;

        foreach ($checklist['items'] as $key => $cy) {
            $answer = data_get($context, "items.{$key}.answer");
            $note = (string) (data_get($context, "items.{$key}.note") ?? '');

            if ($answer === 'yes' || $answer === 'no') {
                $cx = $answer === 'yes' ? $checklist['yes_x'] : $checklist['no_x'];
                $pdf->SetFont('Helvetica', 'B', $size);
                // Center the mark on the box: x by string width, y baseline below the box center.
                $x = $cx - ($pdf->GetStringWidth($mark) / 2);
                $pdf->Text($x, $cy + $size * 0.33, $mark);
            } elseif ($answer === 'na') {
                $note = $note === '' ? 'N/A' : 'N/A — '.$note;
            }

            if ($note !== '') {
                $pdf->SetFont('Helvetica', '', 8);
                $pdf->Text($checklist['note_x'], $cy + 2.5, $this->ascii($note));
            }
        }
    }

    /**
     * Stamp a repeating roster (e.g. CMS-209 personnel rows). For each entry in the `source` list,
     * up to the number of mapped `rows`, render each column at its (x, row-baseline). A column is
     * either a text column (`key` => writes that value) or a check column (`roles` => stamps an X
     * when the entry's `roles` list intersects, used for the CLIA position columns).
     *
     * @param  array{source:string,size?:float,columns:list<array{key?:string,roles?:list<string>,x:float}>,rows:list<float>}|null  $roster
     */
    private function stampRoster(Fpdi $pdf, ?array $roster, array $context): void
    {
        if (! $roster) {
            return;
        }

        $entries = data_get($context, $roster['source']) ?? [];
        $rows = $roster['rows'];
        $size = $roster['size'] ?? 9;

        foreach (array_values($entries) as $i => $entry) {
            if (! isset($rows[$i])) {
                break; // more entries than printed rows; the overflow goes on a continuation sheet
            }
            foreach ($roster['columns'] as $col) {
                if (isset($col['roles'])) {
                    $held = (array) (data_get($entry, 'roles') ?? []);
                    if (array_intersect($col['roles'], $held)) {
                        $pdf->SetFont('Helvetica', 'B', $size);
                        $pdf->Text($col['x'] - ($pdf->GetStringWidth('X') / 2), $rows[$i], 'X');
                    }

                    continue;
                }

                $value = (string) (data_get($entry, $col['key']) ?? '');
                if ($value !== '') {
                    $pdf->SetFont('Helvetica', '', $size);
                    $pdf->Text($col['x'], $rows[$i], $this->ascii($value));
                }
            }
        }
    }

    /**
     * Flatten the response into a value context: top-level answer scalars, the completion date
     * (as `date`), the lab's own details (`lab_name`/`lab_clia`/`lab_address`), and the nested
     * `items` map (keyed by item key) for checklist lookups.
     */
    private function context(FormResponse $form): array
    {
        $answers = $form->answers ?? [];

        $context = Arr::except($answers, ['items']);
        $context['items'] = $answers['items'] ?? [];
        $context['date'] = $form->completed_date?->format('m/d/Y') ?? '';

        if ($form->lab_id && ($lab = Lab::find($form->lab_id))) {
            $context['lab_name'] = (string) $lab->name;
            $context['lab_clia'] = (string) ($lab->clia_number ?? '');
            $context['lab_address'] = (string) ($lab->address ?? '');
            $context['lab_director'] = $lab->profileValue('director_name');
        }

        return $context;
    }

    /** FPDF core fonts are Latin-1; drop anything that would render as garbage (e.g. smart quotes). */
    private function ascii(string $value): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);

        return $converted === false ? $value : $converted;
    }

    private function templatePath(string $code): string
    {
        return resource_path("form-templates/{$code}.pdf");
    }

    private function mapPath(string $code): string
    {
        return app_path("Forms/Overlays/{$code}.php");
    }
}
