<?php

namespace App\Forms;

use InvalidArgumentException;

/**
 * Definitions of the official forms the app can complete via a guided questionnaire.
 *
 * Each form maps to an obligation code (so completing it advances that obligation) and
 * declares its sections / yes-no items. Add new forms here (e.g. CMS-209, CMP-172) — the
 * wizard, storage, and PDF render are all driven off this definition.
 */
class FormCatalog
{
    public const FORMS = [
        'CMP-173' => [
            'title' => 'Laboratory Safety Checklist',
            'obligation_code' => 'C11',
            'route' => 'forms.safety-checklist',
            'pdf_view' => 'forms.safety-checklist-pdf',
            'instructions' => 'Annually, the laboratory will be monitored for safety. Any areas found unsafe will be addressed and corrected.',
            'sections' => [
                [
                    'heading' => 'Egress and House Keeping',
                    'items' => [
                        ['key' => 'egress_unobstructed', 'label' => 'Are egress pathways kept unobstructed and free from trip hazards?'],
                        ['key' => 'surfaces_clean', 'label' => 'Are working surfaces and equipment kept clean & free of chemical residues?'],
                        ['key' => 'sharps_disposal', 'label' => 'Are glass and other "sharps" disposed of in puncture resistant containers?'],
                        ['key' => 'no_food', 'label' => 'Is the consumption or storage of food or beverage prohibited in the laboratory?'],
                    ],
                ],
                [
                    'heading' => 'Emergency Equipment',
                    'items' => [
                        ['key' => 'sink_eyewash', 'label' => 'Is sink eyewash station operational, inspected and flushed weekly?'],
                        ['key' => 'bottle_eyewash', 'label' => 'Is expiration date and seal integrity monitored on bottle eyewash?'],
                        ['key' => 'access_unobstructed', 'label' => 'Are eyewashes, fire extinguishers, & electrical panels kept accessible and unobstructed?'],
                        ['key' => 'spill_kit', 'label' => 'Is a Spill Kit available?'],
                        ['key' => 'first_aid_kit', 'label' => 'Is a First Aid Kit available?'],
                        ['key' => 'safety_manual', 'label' => 'Is Safety Manual available?'],
                        ['key' => 'osha_manual', 'label' => 'Is OSHA Manual available?'],
                        ['key' => 'exit_maps', 'label' => 'Are emergency exit and floor maps posted?'],
                    ],
                ],
                [
                    'heading' => 'Chemical Safety',
                    'items' => [
                        ['key' => 'chem_compatibility', 'label' => 'Are all chemicals stored according to compatibility?'],
                        ['key' => 'chem_cabinets', 'label' => 'Are flammable, corrosive, toxic or reactive chemicals stored in approved chemical storage cabinets or locations?'],
                        ['key' => 'chem_containers_closed', 'label' => 'Are all stored chemical containers kept closed, not leaking and in good condition?'],
                        ['key' => 'chem_labeled', 'label' => 'Are all containers labeled to identify the contents and any applicable hazard warnings?'],
                        ['key' => 'chem_eye_level', 'label' => 'Are all hazardous chemicals stored at or below eye level?'],
                        ['key' => 'flammable_cabinet', 'label' => 'Are flammable liquids in excess of 10 gallons stored in a flammable storage cabinet?'],
                        ['key' => 'waste_containment', 'label' => 'Are hazardous wastes stored away from drains or in secondary containment?'],
                        ['key' => 'sds_available', 'label' => 'Are current SDS available?'],
                    ],
                ],
                [
                    'heading' => 'Compressed Gasses',
                    'items' => [
                        ['key' => 'gas_labeled', 'label' => 'Are compressed gas cylinders properly labeled?'],
                        ['key' => 'gas_secured', 'label' => 'Are compressed gas cylinders adequately secured and stored by compatibility?'],
                        ['key' => 'gas_lines_checked', 'label' => 'Are compressed gas lines and fittings on equipment checked for problems regularly?'],
                    ],
                ],
                [
                    'heading' => 'Personal Protective Equipment (PPE)',
                    'items' => [
                        ['key' => 'ppe_available', 'label' => 'Are lab coats, safety glasses, splash goggles and chemical protective gloves available?'],
                        ['key' => 'ppe_required', 'label' => 'Are safety glasses or splash goggles required to be worn when a splash hazard exists?'],
                        ['key' => 'no_open_toed', 'label' => 'Are open toed shoes prohibited in the laboratory?'],
                    ],
                ],
                [
                    'heading' => 'Ventilation and Engineering Controls',
                    'items' => [
                        ['key' => 'equipment_safeguarded', 'label' => 'Is laboratory equipment properly safeguarded (guards, electrical, interlocks, etc.)?'],
                    ],
                ],
            ],
        ],

        // CMS-209 Laboratory Personnel Report. A 'personnel' form — its roster is pre-filled
        // from the lab's memberships + per-lab roles (lab_user_role), so it's mostly confirm-and-sign.
        'CMS-209' => [
            'title' => 'Laboratory Personnel Report (CMS-209)',
            'obligation_code' => 'C05',
            'route' => 'forms.cms-209',
            'pdf_view' => 'forms.cms-209-pdf',
            'type' => 'personnel',
            'instructions' => 'Lists each person and the CLIA position(s) they hold at this laboratory. Positions are pulled from the lab\'s assigned roles; confirm and add qualifications, then file.',
        ],

        // CMP-172 Reference Laboratory Approval. A short list of approved referral labs + the
        // Lab Director's sign-off (CLIA certificates are attached separately, per the SOP).
        'CMP-172' => [
            'title' => 'Reference Laboratory Approval Form',
            'obligation_code' => 'C10',
            'route' => 'forms.reference-lab-approval',
            'pdf_view' => 'forms.reference-lab-approval-pdf',
            'type' => 'reference_labs',
            'instructions' => 'The following Reference Lab(s) have been approved by the Laboratory Director for use when testing cannot be performed in-house. Attach each designated reference lab\'s CLIA certificate.',
        ],

        // --- Generic, catalog-driven forms (rendered by the ⚡form-wizard component) ---

        // CMP-132 Compliance On-site Visit Checklist -> C04 (Lab Director on-site visit).
        'CMP-132' => [
            'title' => 'Compliance On-site Visit Checklist',
            'obligation_code' => 'C04',
            'component' => 'form-wizard',
            'route' => 'forms.show',
            'pdf_view' => 'forms.generic-pdf',
            'date_field' => 'visit_date',
            'instructions' => 'On-site compliance visit. Confirm each item, note any issues, and record current problems/comments below.',
            'fields' => [
                ['key' => 'compliance_member', 'label' => 'Compliance team member(s)', 'type' => 'text'],
                ['key' => 'client', 'label' => 'Client', 'type' => 'text', 'default' => 'lab_name'],
                ['key' => 'address', 'label' => 'Address', 'type' => 'text'],
                ['key' => 'frequency', 'label' => 'Frequency', 'type' => 'select', 'options' => ['Monthly', 'Quarterly', 'Other']],
                ['key' => 'visit_date', 'label' => 'Visit date', 'type' => 'date', 'default' => 'today'],
                ['key' => 'customer_contact', 'label' => 'Customer contact present?', 'type' => 'select', 'options' => ['Yes', 'No']],
            ],
            'sections' => [
                ['heading' => 'Review', 'items' => [
                    ['key' => 'qa_reviews_prepared', 'label' => 'All QA Reviews prepared per requirements and previous issues corrected?'],
                    ['key' => 'qa_copies_retained', 'label' => 'QA copies retained and filed?'],
                    ['key' => 'client_qa_filed', 'label' => 'Client-prepared QA Reviews copied and filed?'],
                    ['key' => 'meeting_training_filed', 'label' => 'Meeting/Training/In-Service prepared, signed, and filed?'],
                    ['key' => 'staff_minutes_reviewed', 'label' => 'Staff meeting minutes reviewed?'],
                    ['key' => 'pt_qa_prepared', 'label' => 'QA Review for Proficiency Testing prepared (corrective action if grades received)?'],
                    ['key' => 'docs_complete', 'label' => 'All documents complete?'],
                    ['key' => 'training_due', 'label' => 'Training, competency, continuing education up to date?'],
                    ['key' => 'deadlines_met', 'label' => 'All QA-calendar deadlines met (competency, CE, calibration, QC, licensure, certifications)?'],
                    ['key' => 'previous_issues_corrected', 'label' => 'Previous issues reviewed and corrected?'],
                ]],
            ],
            'footer_fields' => [
                ['key' => 'comments', 'label' => 'Current issues, problems, comments', 'type' => 'textarea'],
            ],
        ],

        // CMP-130 Monthly QC Review (Levey-Jennings) -> C12. Narrative review + sign-off; the
        // per-analyte L-J charts are attached separately from the instrument.
        'CMP-130' => [
            'title' => 'Monthly QC Review Form — Levey-Jennings',
            'obligation_code' => 'C12',
            'component' => 'form-wizard',
            'route' => 'forms.show',
            'pdf_view' => 'forms.generic-pdf',
            'date_field' => 'review_date',
            'instructions' => 'Monthly QC review. Attach the per-analyte Levey-Jennings charts; record the review summary, any trends, and corrective action below.',
            'fields' => [
                ['key' => 'review_date', 'label' => 'Review date', 'type' => 'date', 'default' => 'today'],
                ['key' => 'area_of_review', 'label' => 'Area / instrument reviewed', 'type' => 'text'],
                ['key' => 'comments', 'label' => 'Comments (trends, observations)', 'type' => 'textarea'],
                ['key' => 'corrective_action', 'label' => 'Corrective action', 'type' => 'textarea'],
            ],
            'footer_fields' => [
                ['key' => 'completed_by', 'label' => 'Completed by', 'type' => 'text'],
                ['key' => 'lab_director', 'label' => 'Laboratory Director / Designee', 'type' => 'text', 'default' => 'profile:director_name'],
            ],
        ],

        // CMP-131 Quality Assurance Monitor -> C16 (quality assessment program review).
        'CMP-131' => [
            'title' => 'Quality Assurance Monitor',
            'obligation_code' => 'C16',
            'component' => 'form-wizard',
            'route' => 'forms.show',
            'pdf_view' => 'forms.generic-pdf',
            'date_field' => 'review_date',
            'instructions' => 'Monthly QA monitor addressing analytic, pre-analytic, and post-analytic issues. Discuss at the QA meeting to encourage compliance.',
            'fields' => [
                ['key' => 'month_year', 'label' => 'Month / Year', 'type' => 'text'],
                ['key' => 'review_date', 'label' => 'Review date', 'type' => 'date', 'default' => 'today'],
            ],
            'sections' => [
                ['heading' => 'Quality Control', 'items' => [
                    ['key' => 'qc_run', 'label' => 'QC run per lab policy and within acceptable limits before patient results reported?'],
                    ['key' => 'calibration', 'label' => 'Calibration performed/documented at least every 6 months or after major maintenance?'],
                    ['key' => 'materials_stored', 'label' => 'Test materials stored per manufacturer; outdated materials discarded?'],
                    ['key' => 'temps_documented', 'label' => 'Daily temps documented with corrective action where needed?'],
                    ['key' => 'pt_completed', 'label' => 'Proficiency Testing completed, attestation signed, failures investigated/remediated?'],
                ]],
                ['heading' => 'Maintenance', 'items' => [
                    ['key' => 'instrument_maint', 'label' => 'All required instrument maintenance performed and documented?'],
                    ['key' => 'other_maint', 'label' => 'Other maintenance documented (temps, counters, centrifuges)?'],
                ]],
                ['heading' => 'Personnel', 'items' => [
                    ['key' => 'training_documented', 'label' => 'All personnel trained for authorized tests with documentation?'],
                    ['key' => 'competency_current', 'label' => 'Competencies observed, up to date, corrective action where needed?'],
                ]],
                ['heading' => 'Procedures', 'items' => [
                    ['key' => 'specimen_integrity', 'label' => 'Policies ensure specimen identification/integrity from collection through testing, and are followed?'],
                    ['key' => 'procedures_available', 'label' => 'Written test procedures available and followed by lab personnel?'],
                ]],
                ['heading' => 'Safety', 'items' => [
                    ['key' => 'safety_addressed', 'label' => 'Any situation compromising employee safety reported and addressed?'],
                ]],
                ['heading' => 'Other', 'items' => [
                    ['key' => 'confidentiality', 'label' => 'Confidentiality of patient information maintained?'],
                    ['key' => 'two_identifiers', 'label' => 'Specimens labeled with two unique identifiers (not on cup lids)?'],
                    ['key' => 'complaints_addressed', 'label' => 'Complaints/communication problems documented and addressed by the Lab Director?'],
                    ['key' => 'reagent_storage', 'label' => 'Reagent/material expiration dates and storage monitored?'],
                ]],
            ],
            'footer_fields' => [
                ['key' => 'completed_by', 'label' => 'Completed by', 'type' => 'text'],
                ['key' => 'lab_director', 'label' => 'Laboratory Director', 'type' => 'text', 'default' => 'profile:director_name'],
                ['key' => 'qa_meeting_attendees', 'label' => 'QA meeting — others present', 'type' => 'text'],
            ],
        ],

        // CMP-171 New Procedure / Procedure Change -> C15 (new/modified test performance verification).
        // Captures the high-level request; detailed SelectIS/LIS parameters are attached separately.
        'CMP-171' => [
            'title' => 'New Procedure / Procedure Change Form',
            'obligation_code' => 'C15',
            'component' => 'form-wizard',
            'route' => 'forms.show',
            'pdf_view' => 'forms.generic-pdf',
            'date_field' => 'date_submitted',
            'instructions' => 'High-level request for a new or changed test/procedure. Attach full SelectIS/LIS parameters and (for a new procedure) the controls to be used.',
            'fields' => [
                ['key' => 'lab_name', 'label' => 'Laboratory name', 'type' => 'text', 'default' => 'lab_name'],
                ['key' => 'date_submitted', 'label' => 'Date submitted', 'type' => 'date', 'default' => 'today'],
                ['key' => 'request_type', 'label' => 'Request type', 'type' => 'select', 'options' => ['New Procedure', 'Change Request']],
                ['key' => 'change_request', 'label' => 'Change requested', 'type' => 'select', 'options' => ['Add Test', 'Delete Test', 'Add/Alter Panel', 'Delete Panel', 'Other']],
                ['key' => 'test_name', 'label' => 'Test name (as on reports)', 'type' => 'text'],
                ['key' => 'instrument', 'label' => 'Instrument', 'type' => 'text'],
                ['key' => 'enact_date', 'label' => 'Enact date', 'type' => 'date'],
                ['key' => 'toxicology_type', 'label' => 'If toxicology', 'type' => 'select', 'options' => ['Screening', 'Confirmation', 'N/A']],
                ['key' => 'specimen_type', 'label' => 'Specimen type', 'type' => 'text'],
            ],
            'footer_fields' => [
                ['key' => 'lis_notes', 'label' => 'LIS parameters / notes (attach detail)', 'type' => 'textarea'],
                ['key' => 'submitted_by', 'label' => 'Submitted by', 'type' => 'text'],
                ['key' => 'cleared_by', 'label' => 'Cleared by (Lab Director / Manager / CMP Specialist)', 'type' => 'text', 'default' => 'profile:director_name'],
            ],
        ],

        // CMS-116 CLIA Application for Certification -> C01. Federal form; key fields pre-filled from
        // the lab profile. The official CMS-116 PDF is what gets submitted to CMS — this records the data.
        'CMS-116' => [
            'title' => 'CLIA Application for Certification (CMS-116)',
            'obligation_code' => 'C01',
            'component' => 'form-wizard',
            'route' => 'forms.show',
            'pdf_view' => 'forms.generic-pdf',
            'date_field' => 'effective_date',
            'instructions' => 'Captures the key CMS-116 fields for the certificate cycle (pre-filled from the lab profile). Submit the official CMS-116 PDF to CMS / your state agency.',
            'fields' => [
                ['key' => 'lab_name', 'label' => 'Laboratory name', 'type' => 'text', 'default' => 'lab_name'],
                ['key' => 'clia_number', 'label' => 'CLIA #', 'type' => 'text', 'default' => 'lab_clia'],
                ['key' => 'address', 'label' => 'Laboratory address', 'type' => 'text', 'default' => 'lab_address'],
                ['key' => 'director_name', 'label' => 'Laboratory Director', 'type' => 'text', 'default' => 'profile:director_name'],
                ['key' => 'application_type', 'label' => 'Application type', 'type' => 'select', 'options' => ['Initial', 'Recertification', 'Change of information']],
                ['key' => 'certificate_type', 'label' => 'Certificate type', 'type' => 'select', 'options' => ['Compliance', 'Accreditation', 'PPM', 'Waiver'], 'default' => 'profile:certificate_type'],
                ['key' => 'effective_date', 'label' => 'Effective / application date', 'type' => 'date', 'default' => 'today'],
                ['key' => 'test_volume', 'label' => 'Estimated annual test volume', 'type' => 'text', 'default' => 'profile:test_volume'],
                ['key' => 'hours', 'label' => 'Hours of operation', 'type' => 'text', 'default' => 'profile:hours'],
                ['key' => 'specialties', 'label' => 'Specialties / subspecialties offered', 'type' => 'textarea', 'default' => 'profile:specialties'],
            ],
            'footer_fields' => [
                ['key' => 'director_signature', 'label' => 'Director signature (name)', 'type' => 'text', 'default' => 'profile:director_name'],
                ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
            ],
        ],

        // CMP-150 PT Survey Checklist -> C02 (proficiency testing, regulated analytes).
        'CMP-150' => [
            'title' => 'PT Survey Checklist',
            'obligation_code' => 'C02',
            'component' => 'form-wizard',
            'route' => 'forms.show',
            'pdf_view' => 'forms.generic-pdf',
            'date_field' => 'survey_date',
            'instructions' => 'Document handling and performance of a proficiency-testing survey. Attach instrument printouts/worksheets and the signed attestation.',
            'fields' => [
                ['key' => 'survey_title', 'label' => 'Survey title', 'type' => 'text'],
                ['key' => 'assigned_to', 'label' => 'Survey assigned to', 'type' => 'text'],
                ['key' => 'date_received', 'label' => 'Date received in lab', 'type' => 'date'],
                ['key' => 'survey_date', 'label' => 'Date survey completed', 'type' => 'date', 'default' => 'today'],
                ['key' => 'specimen_condition', 'label' => 'Condition of specimens', 'type' => 'select', 'options' => ['Good', 'Broken vials', 'Not refrigerated', 'Missing']],
            ],
            'sections' => [
                ['heading' => 'Survey Review', 'items' => [
                    ['key' => 'instructions_read', 'label' => 'Survey instructions read prior to performing the survey?'],
                    ['key' => 'qc_acceptable', 'label' => 'QC performed and all ranges within acceptable limits?'],
                    ['key' => 'reagents_in_date', 'label' => 'All reagents within expiration date?'],
                    ['key' => 'no_instrument_problems', 'label' => 'Free of instrument problems during the survey and the prior 7 days?'],
                    ['key' => 'printouts_attached', 'label' => 'Instrument printouts and/or worksheets attached?'],
                    ['key' => 'tested_like_patient', 'label' => 'Survey samples tested in the same manner as patient samples?'],
                    ['key' => 'documentation_complete', 'label' => 'Documentation complete and Attestation Statement signed?'],
                ]],
            ],
            'footer_fields' => [
                ['key' => 'general_supervisor', 'label' => 'General Supervisor review', 'type' => 'text', 'default' => 'profile:general_supervisor'],
                ['key' => 'lab_director', 'label' => 'Laboratory Director review', 'type' => 'text', 'default' => 'profile:director_name'],
                ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
            ],
        ],

        // CMP-190 Alternate Proficiency Testing Attestation -> C03 (alternative assessment, non-regulated).
        'CMP-190' => [
            'title' => 'Alternate Proficiency Testing Attestation',
            'obligation_code' => 'C03',
            'component' => 'form-wizard',
            'route' => 'forms.show',
            'pdf_view' => 'forms.generic-pdf',
            'date_field' => 'attestation_date',
            'instructions' => 'Documents alternate proficiency-testing attestation for non-regulated analytes (e.g. LCMS, ETOH). Internal use only — not submitted to a CLIA-approved PT program.',
            'fields' => [
                ['key' => 'prepared_by', 'label' => 'Prepared by', 'type' => 'text'],
                ['key' => 'attestation_date', 'label' => 'Date performed', 'type' => 'date', 'default' => 'today'],
                ['key' => 'analytes', 'label' => 'Analytes / procedures covered', 'type' => 'text'],
                ['key' => 'analyst', 'label' => 'Analyst performing procedure', 'type' => 'text'],
            ],
            'sections' => [
                ['heading' => 'Attestation', 'items' => [
                    ['key' => 'tested_like_patient', 'label' => 'The analyst attests that samples were tested in the same manner as patient samples.'],
                    ['key' => 'blind_submission', 'label' => 'Samples submitted blind by a non-testing analyst (identification removed except Vial # and PT Event ID)?'],
                ]],
            ],
            'footer_fields' => [
                ['key' => 'approver', 'label' => 'Approved by (Lab Director / Technical Consultant / Technical Supervisor)', 'type' => 'text', 'default' => 'profile:director_name'],
            ],
        ],

        // CMP-133 Monthly Remote Monitoring Compliance Checklist -> C09 (patient result / monthly monitoring).
        'CMP-133' => [
            'title' => 'Monthly Remote Monitoring Compliance Checklist',
            'obligation_code' => 'C09',
            'component' => 'form-wizard',
            'route' => 'forms.show',
            'pdf_view' => 'forms.generic-pdf',
            'date_field' => 'review_date',
            'instructions' => 'Monthly remote compliance review of client documents. Confirm each item was reviewed; log any current issues below.',
            'fields' => [
                ['key' => 'client', 'label' => 'Client', 'type' => 'text', 'default' => 'lab_name'],
                ['key' => 'main_contact', 'label' => 'Main contact', 'type' => 'text'],
                ['key' => 'month', 'label' => 'Month', 'type' => 'text'],
                ['key' => 'compliance_member', 'label' => 'Compliance member', 'type' => 'text'],
                ['key' => 'analyzers_reviewed', 'label' => 'Analyzers reviewed', 'type' => 'text'],
                ['key' => 'review_date', 'label' => 'Review date', 'type' => 'date', 'default' => 'today'],
            ],
            'sections' => [
                ['heading' => 'Documents Reviewed from Client', 'items' => [
                    ['key' => 'qc_lj_charts', 'label' => 'QC data & Levey-Jennings charts for prior month?'],
                    ['key' => 'qc_package_inserts', 'label' => 'Current QC package inserts?'],
                    ['key' => 'lot_change_qc', 'label' => 'Lot number change handled with concurrent QC (if applicable)?'],
                    ['key' => 'maintenance_logs', 'label' => 'Maintenance logs?'],
                    ['key' => 'corrective_action_logs', 'label' => 'Corrective action logs?'],
                    ['key' => 'temp_charts', 'label' => 'Temperature chart(s)?'],
                    ['key' => 'pt_results', 'label' => 'Proficiency Testing results?'],
                    ['key' => 'personnel_books', 'label' => 'Personnel books reviewed?'],
                    ['key' => 'procedure_changes', 'label' => 'Procedure/protocol changes reviewed?'],
                    ['key' => 'analyzer_changes', 'label' => 'Analyzer additions/deletions reviewed?'],
                    ['key' => 'ld_visit_emails', 'label' => 'Lab Director visit/emails?'],
                ]],
            ],
            'footer_fields' => [
                ['key' => 'current_issues', 'label' => 'Current issues / problems / problem log', 'type' => 'textarea'],
                ['key' => 'compliance_member_sign', 'label' => 'Completed by', 'type' => 'text'],
            ],
        ],

        // --- Rightsize-owned LOG forms (rendered by the ⚡log-form component) for the numeric/tabular
        // obligations that are lists of entries rather than a questionnaire. Keyed by their RSL SOP code.

        // RSL-DOC-100 Procedure Manual Review & Document Control -> C06.
        'RSL-DOC-100' => [
            'title' => 'Procedure / SOP Review Log',
            'obligation_code' => 'C06',
            'component' => 'log-form',
            'route' => 'forms.show',
            'pdf_view' => 'forms.log-pdf',
            'date_field' => 'review_date',
            'sop_code' => 'RSL-DOC-100',
            'citation' => '42 CFR §493.1251',
            'instructions' => 'Log each procedure/SOP reviewed or revised, with version, changes, and reviewer. Full manual reviewed at least annually and on any change.',
            'fields' => [
                ['key' => 'review_date', 'label' => 'Review date', 'type' => 'date', 'default' => 'today'],
                ['key' => 'lab_director', 'label' => 'Laboratory Director', 'type' => 'text', 'default' => 'profile:director_name'],
            ],
            'columns' => [
                ['key' => 'procedure', 'label' => 'Procedure / SOP'],
                ['key' => 'version', 'label' => 'Version'],
                ['key' => 'changes', 'label' => 'Changes Made'],
                ['key' => 'reviewer', 'label' => 'Reviewer'],
            ],
        ],

        // RSL-EQ-100 Equipment Calibration & Calibration Verification -> C07.
        'RSL-EQ-100' => [
            'title' => 'Calibration / Calibration Verification Log',
            'obligation_code' => 'C07',
            'component' => 'log-form',
            'route' => 'forms.show',
            'pdf_view' => 'forms.log-pdf',
            'date_field' => 'log_date',
            'sop_code' => 'RSL-EQ-100',
            'citation' => '42 CFR §493.1255',
            'instructions' => 'Record each calibration or calibration-verification event. Perform at least every 6 months and after reagent-lot change, major maintenance, or QC-indicated drift.',
            'fields' => [
                ['key' => 'log_date', 'label' => 'Date filed', 'type' => 'date', 'default' => 'today'],
                ['key' => 'reviewed_by', 'label' => 'Reviewed by (Technical Supervisor / Lab Director)', 'type' => 'text', 'default' => 'profile:director_name'],
            ],
            'columns' => [
                ['key' => 'instrument', 'label' => 'Instrument / Analyte'],
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'method', 'label' => 'Material / Method'],
                ['key' => 'criteria', 'label' => 'Acceptance Criteria'],
                ['key' => 'result', 'label' => 'Result (Pass/Fail)'],
                ['key' => 'by', 'label' => 'Performed By'],
            ],
        ],

        // RSL-EQ-110 Pipette & Volumetric Device Verification -> C08.
        'RSL-EQ-110' => [
            'title' => 'Pipette / Volumetric Device Verification Log',
            'obligation_code' => 'C08',
            'component' => 'log-form',
            'route' => 'forms.show',
            'pdf_view' => 'forms.log-pdf',
            'date_field' => 'log_date',
            'sop_code' => 'RSL-EQ-110',
            'citation' => '42 CFR §§493.1254–1255',
            'instructions' => 'Record each device verified, with measured accuracy/precision against tolerance. Verify at least annually and per the manufacturer schedule.',
            'fields' => [
                ['key' => 'log_date', 'label' => 'Date filed', 'type' => 'date', 'default' => 'today'],
                ['key' => 'reviewed_by', 'label' => 'Reviewed by (Technical Supervisor / Lab Director)', 'type' => 'text', 'default' => 'profile:director_name'],
            ],
            'columns' => [
                ['key' => 'device', 'label' => 'Device ID'],
                ['key' => 'volume', 'label' => 'Volume Setting'],
                ['key' => 'target', 'label' => 'Target'],
                ['key' => 'measured', 'label' => 'Measured Mean / %CV'],
                ['key' => 'result', 'label' => 'Pass / Fail'],
                ['key' => 'by', 'label' => 'Date / By'],
            ],
        ],

        // RSL-HR-110 Personnel Qualifications & Credentials -> C13 (event-driven).
        'RSL-HR-110' => [
            'title' => 'Personnel Qualifications & Credentials Record',
            'obligation_code' => 'C13',
            'component' => 'log-form',
            'route' => 'forms.show',
            'pdf_view' => 'forms.log-pdf',
            'date_field' => 'verified_date',
            'sop_code' => 'RSL-HR-110',
            'citation' => '42 CFR §§493.1351–1495',
            'instructions' => 'Record each individual\'s CLIA role, qualifications, and any applicable license/certification with expiration. Verify at hire, role change, and credential renewal.',
            'fields' => [
                ['key' => 'verified_date', 'label' => 'Verification date', 'type' => 'date', 'default' => 'today'],
                ['key' => 'verified_by', 'label' => 'Verified by', 'type' => 'text', 'default' => 'profile:director_name'],
            ],
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'role', 'label' => 'CLIA Role'],
                ['key' => 'qualification', 'label' => 'Qualification / Degree'],
                ['key' => 'license', 'label' => 'License / Cert (No. & Expiry)'],
            ],
        ],

        // RSL-QC-110 Comparison of Test Results (inter-instrument / split-sample) -> C14.
        'RSL-QC-110' => [
            'title' => 'Inter-Instrument / Split-Sample Comparison Log',
            'obligation_code' => 'C14',
            'component' => 'log-form',
            'route' => 'forms.show',
            'pdf_view' => 'forms.log-pdf',
            'date_field' => 'log_date',
            'sop_code' => 'RSL-QC-110',
            'citation' => '42 CFR §493.1281',
            'instructions' => 'Record each comparison where the same analyte is measured by two or more systems. Perform at least twice per year per applicable analyte.',
            'fields' => [
                ['key' => 'log_date', 'label' => 'Date filed', 'type' => 'date', 'default' => 'today'],
                ['key' => 'reviewed_by', 'label' => 'Reviewed by (Laboratory Director)', 'type' => 'text', 'default' => 'profile:director_name'],
            ],
            'columns' => [
                ['key' => 'analyte', 'label' => 'Analyte'],
                ['key' => 'sample', 'label' => 'Sample ID'],
                ['key' => 'inst_a', 'label' => 'Instrument A Result'],
                ['key' => 'inst_b', 'label' => 'Instrument B Result'],
                ['key' => 'diff', 'label' => 'Difference / Within Criteria?'],
                ['key' => 'date', 'label' => 'Date'],
            ],
        ],

        // RSL-QC-120 Individualized Quality Control Plan (IQCP) Annual Review -> C17 (form-wizard).
        // Only relevant to test systems running an IQCP instead of daily 2-level external QC.
        'RSL-QC-120' => [
            'title' => 'IQCP Annual Review',
            'obligation_code' => 'C17',
            'component' => 'form-wizard',
            'route' => 'forms.show',
            'pdf_view' => 'forms.generic-pdf',
            'date_field' => 'review_date',
            'sop_code' => 'RSL-QC-120',
            'citation' => '42 CFR §493.1250',
            'instructions' => 'Annual review of each test system operating under an Individualized Quality Control Plan: confirm the risk assessment remains valid, the QC plan was followed, and reassess after any failures or changes.',
            'fields' => [
                ['key' => 'review_date', 'label' => 'Review date', 'type' => 'date', 'default' => 'today'],
                ['key' => 'test_systems', 'label' => 'Test system(s) under IQCP', 'type' => 'text'],
            ],
            'sections' => [
                ['heading' => 'IQCP Review', 'items' => [
                    ['key' => 'risk_assessment_valid', 'label' => 'Risk assessment (specimen, environment, reagent, test system, testing personnel) reviewed and still valid?'],
                    ['key' => 'qc_plan_followed', 'label' => 'QC plan followed as written and all QC within acceptable limits over the period?'],
                    ['key' => 'failures_addressed', 'label' => 'QC failures / incidents since last review investigated and corrected?'],
                    ['key' => 'changes_evaluated', 'label' => 'Reagent, instrument, software, or personnel changes evaluated for impact on the plan?'],
                    ['key' => 'quality_assessment_reviewed', 'label' => 'Quality assessment data (error rates, trends) support continued use of the IQCP?'],
                    ['key' => 'iqcp_reapproved', 'label' => 'IQCP re-approved (or revised) and signed by the Laboratory Director?'],
                ]],
            ],
            'footer_fields' => [
                ['key' => 'completed_by', 'label' => 'Completed by', 'type' => 'text'],
                ['key' => 'lab_director', 'label' => 'Laboratory Director', 'type' => 'text', 'default' => 'profile:director_name'],
            ],
        ],
    ];

    /**
     * Rightsize-owned SOP identity per form: the SOP number the generated PDF carries and the
     * primary regulatory citation shown in its footer. These are now Rightsize's own documents
     * (replacing the former SLP CMP-### forms); the two federal forms reference, not replace, the
     * official CMS document. See OneDrive/Documents/RSL/Rightsize-SOPs.
     */
    public const SOP_META = [
        'CMP-173' => ['sop_code' => 'RSL-S-100', 'citation' => '42 CFR §493.1101; OSHA 29 CFR 1910.1450'],
        'CMP-130' => ['sop_code' => 'RSL-QC-100', 'citation' => '42 CFR §493.1256'],
        'CMP-150' => ['sop_code' => 'RSL-PT-100', 'citation' => '42 CFR §493.801'],
        'CMP-190' => ['sop_code' => 'RSL-PT-110', 'citation' => '42 CFR §493.1236(c)'],
        'CMP-132' => ['sop_code' => 'RSL-QA-100', 'citation' => '42 CFR §493.1445'],
        'CMP-133' => ['sop_code' => 'RSL-QA-110', 'citation' => '42 CFR §§493.1289–1299'],
        'CMP-131' => ['sop_code' => 'RSL-QA-120', 'citation' => '42 CFR §§493.1239–1299'],
        'CMP-172' => ['sop_code' => 'RSL-RL-100', 'citation' => '42 CFR §493.1242'],
        'CMP-171' => ['sop_code' => 'RSL-MV-100', 'citation' => '42 CFR §493.1253'],
        'CMS-116' => ['sop_code' => 'RSL-ADM-100', 'citation' => '42 CFR Part 493, Subpart F'],
        'CMS-209' => ['sop_code' => 'RSL-HR-100', 'citation' => '42 CFR §493.1235'],
    ];

    public static function get(string $code): array
    {
        if (! isset(self::FORMS[$code])) {
            throw new InvalidArgumentException("Unknown form code: {$code}");
        }

        return self::FORMS[$code] + (self::SOP_META[$code] ?? []) + ['code' => $code];
    }

    /** The form (if any) whose completion satisfies the given obligation code. */
    public static function forObligation(string $obligationCode): ?array
    {
        foreach (self::FORMS as $code => $def) {
            if (($def['obligation_code'] ?? null) === $obligationCode) {
                return self::get($code);
            }
        }

        return null;
    }

    /** Flat list of every item key across all sections (handy for defaults/validation). */
    public static function itemKeys(string $code): array
    {
        $keys = [];
        foreach (self::get($code)['sections'] ?? [] as $section) {
            foreach ($section['items'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }
}
