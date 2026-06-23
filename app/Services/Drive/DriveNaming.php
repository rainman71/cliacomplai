<?php

namespace App\Services\Drive;

use App\Models\Obligation;
use Carbon\CarbonImmutable;

/**
 * The standard Drive folder template and file-naming convention (plan Feature C2).
 *
 * Folder:   "{YEAR} Annual Documents/{Category Folder}"
 * Filename: "{CODE}_{YYYY-MM-DD}_{Description}_signed.pdf"
 *   e.g.    C02_2026-06-15_PT_regulated_analytes_AAB_signed.pdf
 */
class DriveNaming
{
    /** obligation category -> annual sub-folder name. */
    private const CATEGORY_FOLDER = [
        'CLIA Certification' => 'CLIA Certification',
        'Proficiency Testing' => 'Proficiency Testing',
        'Lab Director' => 'Lab Director Visits',
        'Personnel' => 'Personnel Competency',
        'Procedures' => 'Procedures',
        'Equipment' => 'Equipment Calibration',
        'Patient Testing' => 'Patient Result Approvals',
        'Reference Lab' => 'Reference Lab Approval',
        'Safety' => 'Safety',
        'Quality Control' => 'Quality Control',
    ];

    /** Per-obligation overrides where the category folder isn't a clean fit. */
    private const CODE_FOLDER = [
        'C08' => 'Pipette Checks',
    ];

    /**
     * Folder path segments under the Drive root, e.g. ["2026 Annual Documents", "Proficiency Testing"].
     *
     * @return array<int, string>
     */
    public function folderSegments(Obligation $obligation, string $date): array
    {
        $year = CarbonImmutable::parse($date)->year;
        $folder = self::CODE_FOLDER[$obligation->code]
            ?? self::CATEGORY_FOLDER[$obligation->category]
            ?? $obligation->category;

        return ["{$year} Annual Documents", $folder];
    }

    public function filename(Obligation $obligation, string $date): string
    {
        $desc = preg_replace('/[^A-Za-z0-9]+/', '_', $obligation->name);
        $desc = trim($desc, '_');

        return "{$obligation->code}_{$date}_{$desc}_signed.pdf";
    }
}
