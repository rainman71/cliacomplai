<?php

/**
 * Coordinate overlay map for the federal CMS-116 CLIA Application for Certification (Track B).
 *
 * Like CMS-209, the official cms.gov CMS-116 is a true fillable AcroForm (10 pages, 297 named
 * fields). Positions below were read straight from the form's field geometry (field name + rect),
 * then the widgets were flattened so the overlay sits on the printed form. Values come from the
 * CMS-116 form-wizard answers (stored under `fields.*`).
 *
 * Scope: this fills the lab-identity core (name, CLIA #, address, director), the application-type
 * and certificate-type checkboxes, effective date, the grand total annual test volume, and the
 * page-5 printed director name + date. The detailed per-specialty test-category sections and the
 * ink signature are completed on the official form by the lab — the app captures only a summary.
 *
 * application_type mapping: 'Initial'->Initial Application, 'Change of information'->Change in
 * Certificate Type. 'Recertification' has no clean CMS-116 checkbox and is left unmarked.
 * certificate_type maps 1:1 (Compliance/Accreditation/PPM/Waiver).
 *
 * PDF points, origin top-left. Consumed by FormOverlayRenderer.
 */
return [
    'template' => 'CMS-116.pdf',

    'text' => [
        // --- Page 1: laboratory identity ---
        ['key' => 'fields.clia_number', 'x' => 306, 'y' => 140, 'size' => 9],   // CLIA Identification Number
        ['key' => 'fields.effective_date', 'x' => 97, 'y' => 203, 'size' => 9], // Effective date
        ['key' => 'fields.lab_name', 'x' => 34, 'y' => 234, 'size' => 10],      // Facility name
        ['key' => 'fields.address', 'x' => 34, 'y' => 325, 'size' => 9],        // Facility address (street)
        ['key' => 'fields.director_name', 'x' => 34, 'y' => 448, 'size' => 9],  // Name of director
        // --- Page 4: totals ---
        ['key' => 'fields.test_volume', 'x' => 533, 'y' => 702, 'size' => 9, 'page' => 4], // Total est. annual test volume
        // --- Page 5: applicant attestation ---
        ['key' => 'fields.director_name', 'x' => 34, 'y' => 667, 'size' => 10, 'page' => 5], // Print name of owner/director
        ['key' => 'date', 'x' => 483, 'y' => 687, 'size' => 9, 'page' => 5],    // Date (signed)
    ],

    'checks' => [
        // Application type
        ['key' => 'fields.application_type', 'equals' => 'Initial', 'x' => 39, 'y' => 121],
        ['key' => 'fields.application_type', 'equals' => 'Change of information', 'x' => 39, 'y' => 155],
        // Certificate type
        ['key' => 'fields.certificate_type', 'equals' => 'Waiver', 'x' => 39, 'y' => 526],
        ['key' => 'fields.certificate_type', 'equals' => 'PPM', 'x' => 39, 'y' => 563],
        ['key' => 'fields.certificate_type', 'equals' => 'Compliance', 'x' => 39, 'y' => 580],
        ['key' => 'fields.certificate_type', 'equals' => 'Accreditation', 'x' => 39, 'y' => 597],
    ],
];
