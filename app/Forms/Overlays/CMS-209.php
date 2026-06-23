<?php

/**
 * Coordinate overlay map for the federal CMS-209 Laboratory Personnel Report.
 *
 * Unlike the proprietary CMP-### forms, the official CMS-209 ships as a true fillable AcroForm
 * (from cms.gov). Every position below was read straight from the PDF's own form-field geometry
 * (field name + rect) — NOT measured by hand — then the interactive widgets were flattened out so
 * the overlay sits on the printed form. See scripts/normalize-pdf-templates.py / the prep step.
 *
 * PDF points, origin top-left. Page is US Letter (612 x 792). Consumed by FormOverlayRenderer.
 *
 * The per-person CLIA position columns are stamped from each person's app roles:
 *   lab_director→LD, tech_supervisor→TS, general_supervisor→GS, tech_staff→TP. The CLIA columns
 *   with no app equivalent (CC, TC, CT/GS, CT) and the non-testing roles (admin, compliance,
 *   safety_officer) are left blank for the lab to mark by hand, as is the M/H complexity column.
 */
return [
    'template' => 'CMS-209.pdf',

    'text' => [
        ['key' => 'lab_name', 'x' => 39, 'y' => 102.2, 'size' => 10],   // 1. Laboratory name
        ['key' => 'clia_number', 'x' => 442, 'y' => 102.2, 'size' => 10], // 2. CLIA #
        ['key' => 'lab_address', 'x' => 39, 'y' => 128.7, 'size' => 9],  // 3. Address (number/street)
        ['key' => 'date', 'x' => 494, 'y' => 253.0, 'size' => 9],        // Date of survey
        ['key' => 'lab_director', 'x' => 45, 'y' => 748.0, 'size' => 10], // 6. Printed name of lab director
        ['key' => 'date', 'x' => 449, 'y' => 748.0, 'size' => 9],         // 7. Date
    ],

    // Personnel rows. Name column (field "EMPLOYEE NAMES 1..14" — Last/First/MI merged into one
    // AcroForm field) plus the CLIA position columns, stamped with an X from the person's roles.
    'roster' => [
        'source' => 'people',
        'size' => 9,
        'columns' => [
            ['key' => 'name', 'x' => 45],
            ['roles' => ['lab_director'], 'x' => 267.5],     // LD
            ['roles' => ['tech_supervisor'], 'x' => 326.2],  // TS
            ['roles' => ['general_supervisor'], 'x' => 344.7], // GS
            ['roles' => ['tech_staff'], 'x' => 364.3],       // TP
        ],
        'rows' => [
            311.9, 332.2, 352.5, 372.8, 393.1, 413.4, 433.7,
            454.0, 474.2, 494.5, 514.8, 535.1, 555.4, 575.7,
        ],
    ],
];
