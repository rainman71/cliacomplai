<?php

namespace App\Services;

use App\Models\Lab;

/**
 * Creates a lab and clones the standard 17-obligation CLIA register into it (the template).
 * Each lab gets its own independent obligations + required signers.
 */
class LabProvisioner
{
    /**
     * Template: code, category, name, frequency, interval(months), owner, [required signers].
     * Cadences reflect general CLIA expectations — confirm per each lab's certificate/PT enrollment.
     */
    public const TEMPLATE = [
        ['C01', 'CLIA Certification', 'CLIA Certificate of Compliance renewal (CMS-116)', 'Every 2 years', 24, 'Lab Director', ['Lab Director']],
        ['C02', 'Proficiency Testing', 'PT — regulated analytes (AAB)', '3 events / year', 4, 'Tech Supervisor', ['Lab Director', 'Tech Supervisor']],
        ['C03', 'Proficiency Testing', 'Alternative assessment — no PT program (LCMS, ETOH)', 'At least 2 / year', 6, 'Tech Supervisor', ['Tech Supervisor']],
        ['C04', 'Lab Director', 'Lab Director on-site visit', '2 / year', 6, 'Lab Director', ['Lab Director']],
        ['C05', 'Personnel', 'Personnel competency assessment', 'At hire, 6 & 12 mo (yr 1), then annual', 12, 'Tech Supervisor', ['Tech Supervisor', 'Lab Director']],
        ['C06', 'Procedures', 'Procedure / SOP review', 'Annual (and on change)', 12, 'Lab Director', ['Lab Director']],
        ['C07', 'Equipment', 'Equipment calibration & verification', 'At least every 6 months', 6, 'General Supervisor', ['General Supervisor']],
        ['C08', 'Equipment', 'Pipette verification', 'Annual', 12, 'Technical staff', ['Tech Supervisor']],
        ['C09', 'Patient Testing', 'Patient result approval', 'Monthly review', 1, 'Lab Director', ['Lab Director / designee']],
        ['C10', 'Reference Lab', 'Reference lab approval (designated referral)', 'Annual', 12, 'Lab Director', ['Lab Director']],
        ['C11', 'Safety', 'Lab safety check', 'Annual', 12, 'Safety Officer', ['General Supervisor']],
        ['C12', 'Quality Control', 'QC review', 'Monthly', 1, 'Tech Supervisor', ['Tech Supervisor']],
        ['C13', 'Personnel', 'Personnel licenses & credentials', 'Per credential expiry', null, 'Compliance Specialist', ['Compliance Specialist']],
        // Added 2026-06-21 after CLIA verification (see CLIA_CITATIONS.md). C14 applies only to
        // labs reporting the same analyte from 2+ instruments/methods — deactivate if N/A.
        ['C14', 'Quality Control', 'Comparison of test results (inter-instrument/method correlation)', 'Twice a year', 6, 'Tech Supervisor', ['Tech Supervisor']],
        ['C15', 'Method Validation', 'New/modified test performance verification (accuracy, precision, range, ref intervals)', 'Before reporting a new/modified test', null, 'Tech Supervisor', ['Tech Supervisor', 'Lab Director']],
        ['C16', 'Quality Assessment', 'Quality assessment program review (pre-analytic / analytic / post-analytic)', 'Annual', 12, 'Lab Director', ['Lab Director']],
        // Added 2026-06-23. Applies only to labs running an Individualized Quality Control Plan
        // (§493.1250) instead of daily 2-level external QC — deactivate if the lab runs daily 2-level QC.
        ['C17', 'Quality Control', 'Annual IQCP review (risk assessment + QC plan, per test system)', 'Annual', 12, 'Tech Supervisor', ['Tech Supervisor', 'Lab Director']],
    ];

    public function create(array $attributes): Lab
    {
        $lab = Lab::create($attributes);
        $this->seedObligations($lab);

        return $lab;
    }

    /** Clone the 17-obligation template into a lab (idempotent on code). */
    public function seedObligations(Lab $lab): void
    {
        foreach (self::TEMPLATE as [$code, $category, $name, $freq, $interval, $owner, $signers]) {
            $obligation = $lab->obligations()->updateOrCreate(
                ['code' => $code],
                [
                    'category' => $category,
                    'name' => $name,
                    'frequency_label' => $freq,
                    'interval_months' => $interval,
                    'owner_role' => $owner,
                    'signature_status' => 'not_started',
                    'active' => true,
                ]
            );

            $obligation->requiredSigners()->delete();
            foreach ($signers as $i => $role) {
                $obligation->requiredSigners()->create(['sign_order' => $i + 1, 'signer_role' => $role]);
            }
        }
    }
}
