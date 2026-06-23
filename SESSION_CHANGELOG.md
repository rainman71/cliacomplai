# Session Change Log — 2026-06-21 (autonomous)

Record of everything changed this session so it can be reviewed/undone (this project is not a
git repo). Grouped by feature. To undo a feature: delete its **new files** and revert the
**edits** noted. All changes are code/data only — nothing deployed, no external services touched.
**Test status at end of session: 72 passing (209 assertions).**

---

## 1. C11/C12 date verification + cadence fix
- **Edited** `app/Services/LabProvisioner.php` — C11 cadence Monthly→Annual (`interval_months` 1→12) in `TEMPLATE`.
- **Edited** `tests/Feature/ReminderTest.php` — C11 baseline date in `test_weekly_digest_lists_overdue` (annual cadence).
- **DB (dev)**: Lab #1 C11 → interval 12, freq "Annual", next_due 2024-12-18; C12 last_completed → 2026-04-11; C11/C12 `document_link`s repointed to signed evidence files. Lab #2 C11 → annual.
  - *Undo*: re-run the relevant dates or `php artisan migrate:fresh --seed` (dev only — discards data).

## 2. Register expanded 13 → 16 obligations
- **Edited** `app/Services/LabProvisioner.php` — added C14 (comparison §493.1281), C15 (method validation §493.1253), C16 (QA review §493.1239/1249/1289/1299) to `TEMPLATE`.
- **Edited** count assertions: `tests/Feature/{CompletenessReportTest,LabAdminTest,UserManagementTest}.php`, `tests/TestCase.php` (13→16 / 26→32).
- **DB (dev)**: backfilled C14–C16 into Lab #1 and Lab #2.
- **New** `CLIA_CITATIONS.md` — per-obligation 42 CFR Part 493 citations + gaps (reference doc).

## 3. Guided form-wizard feature
**New infrastructure:**
- `database/migrations/2026_06_21_100000_create_form_responses_table.php` (+ ran migrate on dev DB)
- `app/Models/FormResponse.php`
- `app/Forms/FormCatalog.php` — all form definitions (11 forms)
- `app/Services/FormService.php` — stores answers + records completion + advances obligation
- `app/Http/Controllers/FormPdfController.php` — form-agnostic PDF download

**Bespoke form components (3):**
- `resources/views/components/⚡safety-checklist.blade.php` + `SafetyChecklistController.php` + `forms/safety-checklist{,.-pdf}` views (CMP-173 → C11)
- `resources/views/components/⚡cms-209-report.blade.php` + `Cms209Controller.php` + `forms/cms-209{,-pdf}` views (CMS-209 → C05)
- `resources/views/components/⚡reference-lab-approval.blade.php` + `ReferenceLabApprovalController.php` + `forms/reference-lab-approval{,-pdf}` views (CMP-172 → C10)

**Generic engine + catalog-driven forms (8 entries):**
- `resources/views/components/⚡form-wizard.blade.php` — renders any catalog entry with `component: 'form-wizard'`
- `app/Http/Controllers/FormController.php` — `forms.show` dispatcher
- `resources/views/forms/generic{,-pdf}.blade.php`
- Catalog entries (no per-form code): CMP-132→C04, CMP-130→C12, CMP-131→C16, CMP-171→C15, CMS-116→C01, CMP-150→C02, CMP-190→C03, CMP-133→C09

**Edits:**
- `routes/web.php` — added form routes (`forms.safety-checklist`, `forms.cms-209`, `forms.reference-lab-approval`, `forms.pdf`, `forms.show`).
- `resources/views/components/⚡compliance-dashboard.blade.php` — green "Fill {code} →" link on register rows with a form.
- `tests/Feature/FormWizardTest.php` — **new** test file (13 tests).
  - *Undo*: delete the new files above, remove the form routes, and remove the register-link `@php`/`@if` block in the dashboard.

## 4. Cross-lab merged overdue worklist
- **New** `app/Http/Controllers/WorklistController.php`, `resources/views/worklist.blade.php`, `tests/Feature/WorklistTest.php`
- **Edited** `routes/web.php` (route `worklist`), `resources/views/executive.blade.php` (link to worklist).
  - *Undo*: delete the new files, remove the `worklist` route + the executive-page link.

## 5. Drive filing of generated form PDFs (2026-06-22)
- **Edited** `app/Services/Drive/DriveFiler.php` — added `fileGeneratedPdf(obligation, completion, contents, fallbackLink)` to the interface.
- **Edited** `app/Services/Drive/NullDriveFiler.php` — implements it (logs only; returns fallback link).
- **Edited** `app/Services/Drive/GoogleDriveFiler.php` — implements it (uploads the PDF bytes into the folder tree, returns Drive `webViewLink`).
- **Edited** `app/Services/FormService.php` — after a form completes, renders the PDF (dompdf) and files it best-effort; only when the filer `isConfigured()` (so dev/test keep the in-app PDF link and stay fast); a Drive failure never rolls back the submission.
- **Edited** `tests/Feature/FormWizardTest.php` — added a test that binds a configured stub filer and asserts the obligation/response `document_link` + `drive_file_id` are updated to the Drive link.
- *Net effect:* once the Google Drive service account is configured, completing any in-app form auto-files its PDF into the lab's Drive tree; until then the in-app PDF route is the link. **73 tests pass.**
  - *Undo*: revert the four `app/` files and remove the new test; the interface change is the only cross-cutting edit.

## 6. Completions-history reconciliation (2026-06-22)
- **New** `app/Console/Commands/BackfillBaselineCompletions.php` — `php artisan compliance:backfill-completions [--dry-run]`. Creates one baseline `completions` row per obligation that has a `last_completed` date but no completion (created_by null = migrated baseline, not an in-app event). Idempotent.
- **New** `tests/Feature/BackfillCompletionsTest.php` (2 tests).
- **DB (dev)**: ran the command on Lab #1 → created 12 baseline completions (C01–C12). C13–C16 skipped (no baseline date).
  - *Undo*: delete the 12 baseline completion rows (the ones with `created_by IS NULL`) for Lab #1, and delete the command + test. (`compliance:backfill-completions` is re-runnable/idempotent, so re-applying is safe.)

## 7. Auto-ingestion engine — track #4 (2026-06-22)
Detect signed evidence in a lab's Drive and advance matching obligations. Brain is built + tested now; the "eyes" (GoogleDriveScanner) activate when the service account is configured.
- **New** `app/Services/Drive/DiscoveredFile.php`, `DriveScanner.php` (interface), `NullDriveScanner.php`, `GoogleDriveScanner.php`, `EvidenceIngestor.php`.
- **New** `app/Console/Commands/IngestEvidence.php` — `php artisan compliance:ingest-evidence [--apply]` (preview by default).
- **New** `tests/Feature/EvidenceIngestionTest.php` (4 tests, fake scanner).
- **Edited** `app/Providers/AppServiceProvider.php` — bind `DriveScanner` (Google when creds present, else Null).
- Mechanism: filename `CMP-173_..._signed_2023.12.18.pdf` → form code → obligation (via `FormCatalog`) → completion (created_by null) advancing the obligation; idempotent via Drive file id; only advances forward.
  - *Undo*: delete the new Drive/* scanner files, the command, the test; remove the `DriveScanner` binding in AppServiceProvider.

## 8. Stale / last-verified indicator — track #4 (2026-06-22)
- **Edited** `app/Services/ComplianceStatusService.php` — added `daysSinceVerified()` + `isStale()`; `for()` now also returns `days_since_verified` + `stale` (additive — existing consumers unaffected).
- **Edited** `resources/views/components/⚡compliance-dashboard.blade.php` — register `rows()` carries the new fields; a subtle amber "⚠ not verified · Nd" badge shows on stale rows.
- **Edited** `tests/Feature/ComplianceStatusTest.php` — added stale-logic tests.
  - Staleness = no in-app update within the obligation's review window (interval months → days, or 1 year for event-driven). Shows nothing today (data freshly loaded); surfaces drift over time.
  - *Undo*: revert the three files.

## 9. Drive write safety — never overwrite, archive previous versions (2026-06-22)
Refactored Drive filing behind a `DriveClient` port so the filing/archiving logic is unit-testable without live creds, and added archive-on-collision so re-files are never lost or overwritten.
- **New** `app/Services/Drive/DriveClient.php` (port: findFolder/createFolder/findFile/uploadPdf/moveRename), `app/Services/Drive/GoogleDriveClient.php` (real Google impl — thin I/O), `tests/Support/FakeDriveClient.php` (in-memory fake).
- **Edited** `app/Services/Drive/GoogleDriveFiler.php` — now orchestrates over `DriveClient`. `fileGeneratedPdf`: if a file with the canonical name already exists in the target folder, the existing one is **moved into an `Archived/` subfolder (renamed `.superseded_<ts>`) before** the new file is written. Folders are reused (find-then-create). **Nothing is ever overwritten or deleted.**
- **Edited** `app/Providers/AppServiceProvider.php` — `GoogleDriveFiler` now gets a `GoogleDriveClient`.
- **New** `tests/Feature/GoogleDriveFilerTest.php` (3 tests: first filing = 1 file/no archive; re-file archives the prior version & keeps new as current; folders reused not duplicated).
- Behavior summary for the user's question: filing **only ever creates new files** (`files.create`), the signature flow only moves/renames a file it already owns by id, the scanner is read-only, and ingestion writes only to the local DB. Same-name re-files now archive the prior version instead of duplicating or overwriting. **84 tests pass.**
  - *Undo*: revert `GoogleDriveFiler.php` + the AppServiceProvider binding; delete `DriveClient.php`, `GoogleDriveClient.php`, `tests/Support/FakeDriveClient.php`, `tests/Feature/GoogleDriveFilerTest.php`.

## 10. Drive verification + Shared Drive support (2026-06-22)
- **New** `app/Console/Commands/DriveCheck.php` — `php artisan compliance:drive-check [--write-test]`. Read-only diagnostic (confirms creds detected, filer/scanner configured, per-lab read access); `--write-test` uploads one clearly-named probe PDF (`_App Write Test/`) to confirm write + surface the SA storage-quota gotcha.
- **Edited** `app/Services/Drive/GoogleDriveClient.php` + `GoogleDriveScanner.php` — added `supportsAllDrives` / `includeItemsFromAllDrives` to all calls so the SA can read/write **Shared Drives** (was 404'ing on the Shared Drive folder without them).
- **Verified live (2026-06-22):** service-account key configured (`.config/drive-sa.json`), root `18fXxFA4…` (a Shared Drive). Read OK (sees evidence), write OK (probe uploaded, no quota error). Left a probe file at `_App Write Test/` (deletable).
  - *Undo*: delete `DriveCheck.php`; revert the flag additions in `GoogleDriveClient`/`GoogleDriveScanner` (safe to keep — purely additive).
- **Open before running ingestion `--apply`:** (a) set per-lab `drive_root_folder_id` so labs don't cross-ingest each other's evidence (currently both labs share one global query); (b) the scanner matches direct children when a root is set / a 200-capped global query otherwise — the nested TBR tree needs **recursive, per-lab scoped** scanning for complete + safe ingestion. **Do not run `compliance:ingest-evidence --apply` until (a)+(b) are done.**

## 11. Test isolation from real Drive + incident note (2026-06-22)
- **Incident:** after the service-account key was configured in `.env`, a full `php artisan test` run inherited those creds and the form-wizard tests uploaded **14 test PDFs into the real Shared Drive** (under `18fXxFA4…`), plus the `_App Write Test/` probe. Nothing real was overwritten/deleted — all additive, names end `_signed.pdf` / `.superseded_…` / `app-write-test_…`, created 2026-06-22 ~14:33–14:36.
- **Fix:** `phpunit.xml` now sets `GOOGLE_DRIVE_CREDENTIALS=""` and `GOOGLE_DRIVE_ROOT_FOLDER_ID=""` so tests always use the Null filer/scanner regardless of `.env`. Suite back to 84 passing in ~7s (no Drive calls).
- **Cleanup: PENDING user decision** — the 14 test files were left in the Drive (trashing real-Drive files needs explicit OK; user did not confirm). When approved, add a one-off `compliance:drive-cleanup` that trashes them by id (dry-run first; Drive trash is reversible). Manual alternative: trash today's `*_signed.pdf` files + the `_App Write Test/` folder in the Shared Drive.

## 12. Recursive, per-lab-scoped Drive scanning (2026-06-22)
- **Edited** `app/Services/Drive/DriveClient.php` + `GoogleDriveClient.php` — added `listChildren()` (paginated, supportsAllDrives). `tests/Support/FakeDriveClient.php` — added `listChildren()`.
- **Rewrote** `app/Services/Drive/GoogleDriveScanner.php` — now takes a `DriveClient` and does a BFS over the lab's `drive_root_folder_id` subtree (any depth), collecting `*_signed_*` PDFs; cycle-guarded. **Scoped strictly to the lab's root — empty root ⇒ scans nothing** (no more global query, so labs can't cross-ingest). Replaces the old direct-children-only / 200-capped query.
- **Edited** `app/Providers/AppServiceProvider.php` — scanner binding injects `GoogleDriveClient`.
- **New** `tests/Feature/GoogleDriveScannerTest.php` (2 tests: recurses nested subfolders & filters to signed PDFs / excludes siblings; no-root ⇒ empty). **86 tests pass.**
- **DB (dev):** set Lab #1 `drive_root_folder_id = 18fXxFA4…` (the Shared Drive root) so its scan is scoped + live-verifiable. Lab #2 still has none (scans nothing). Per-lab roots are editable in Manage Labs.
- **Live-verified (2026-06-22):** Lab #1 recursive scan found **435 signed PDFs** (vs 0 with the old direct-children query, and >200 so pagination works); Lab #2 = 0 (scoped). The 14 earlier test files end `_signed.pdf` (not the `_signed_<date>` DocuSign pattern) so the scanner excludes them — inert for ingestion. Note: scanning 435 files took ~1 min (one API call per folder) — narrowing Lab #1's root to the specific TBR compliance subfolder would be faster + tighter.
  - *Undo*: revert the four `app/` files + binding; delete the test; clear Lab #1's drive_root_folder_id.

## 13. Per-lab roots set + first real auto-ingestion applied (2026-06-22)
- **DB (dev):** Lab #1 `drive_root_folder_id` → `1WipKv4…` (the existing "Triad Behavioral Resources" folder, not the whole Shared Drive — tighter + excludes stray test folders). Lab #2 → a new dedicated folder `1rqkUlJx4…` created via the SA (empty → scans nothing).
- **Ran `compliance:ingest-evidence --apply`** (writes to local DB only; never to Drive). Dry-run found 2 candidates; applied:
  - **C05** Personnel competency: 2026-02-16 → **2026-02-17** (latest signed CMS-209).
  - **C16** QA program review: *(none)* → **2024-12-31** (latest signed CMP-131 QA Monitor) → now correctly **OVERDUE** (real gap: no QA monitor since Dec 2024).
  - Each created a completion (`created_by` null = auto-ingested) with `document_link`/`drive_file_id` pointing at the real signed PDF in Drive; audit action `evidence_ingest`. Forward-only + idempotent (re-running ingests nothing new).
  - *Undo:* delete those two completions + reset C05 last_completed→2026-02-16 / C16→null (and clear next_due). All other obligations already matched the filed evidence (0 further candidates).

## 14. Scheduled daily auto-ingestion (2026-06-22)
- **Edited** `routes/console.php` — `Schedule::command('compliance:ingest-evidence --apply')->dailyAt('06:30')->withoutOverlapping();` (runs 30 min before the 07:00 reminders so the register is in sync first; no-ops if Drive unconfigured). Verified via `php artisan schedule:list`. 86 tests pass.
- **Prod dependency (already required for reminders):** the OS cron `* * * * * php artisan schedule:run` must exist on the host (see DEPLOY.md §5) for any scheduled command to fire automatically. Locally it's defined and runs on demand via `php artisan schedule:run`.
  - *Undo:* remove the `compliance:ingest-evidence` line from `routes/console.php`.

## 15. User manual (2026-06-22)
- **New** `USER_MANUAL.md` (project root) — end-user guide: sign-in/roles, portfolio + dashboard tabs, statuses, completing obligations (register edit / forms / Drive sync), the 11 form wizards, signatures, reports, multi-lab views, reminders, Drive auto-filing & daily sync, admin tasks, roles & 16-obligation reference, FAQ. *(Undo: delete the file.)*
- **New** `USER_MANUAL.docx` (project root) — polished Word version of the same manual (title page, TOC, formatted tables, header/footer with page numbers), generated via the docx library. Note: the skill's Python validator couldn't run (no real Python on this host — only the Windows Store stub); validated instead by confirming all 26 zip parts present + every XML part well-formed. *(Undo: delete the file.)*

## 16. Register usability — pinned Actions column + slimmer inputs (2026-06-22)
- **Issue:** the Full Register's Actions column (holding "Fill {code} →" and "Send for signature") sat off the right edge of the wide table and was only reachable by horizontal scroll — poor discoverability.
- **Edited** `resources/views/components/⚡compliance-dashboard.blade.php`: pinned the last column (`sticky right-0 z-10/20`, opaque bg, left border) so actions stay visible while scrolling; renamed its header from a duplicate "Signature" to **"Actions"**; narrowed inputs (Last Completed w-36→w-32, Document link w-44→w-32, Notes w-44→w-28) to slim the table.
- Ran `npm run build` (compiles the new `sticky`/`right-0`/`z-*`/`border-l` utilities) + `view:clear`. Dashboard tests pass. **NOTE for future UI work: after editing Blade for new Tailwind classes, re-run `npm run build` or the styles won't appear** (this is what hid the green "Fill" link earlier).
  - *Undo:* revert the dashboard blade edits + `npm run build`.

## 17. Lab profile + form auto-fill (2026-06-22)
- **New** migration `2026_06_22_000001_add_profile_to_labs.php` — JSON `profile` column on `labs` (ran on dev DB).
- **Edited** `app/Models/Lab.php` — `profile` fillable + array cast; `PROFILE_FIELDS` constant (director, supervisors, phone, hours, certificate type, test volume, specialties); `profileValue()` helper.
- **Edited** `⚡form-wizard.blade.php` — added a `resolveDefault()` that supports a new `profile:<key>` default token (alongside today/lab_name/lab_clia/lab_address).
- **Edited** `app/Forms/FormCatalog.php` — set `default` tokens so forms pre-fill from the profile: CMS-116 (director, certificate type, test volume, hours, specialties, director signature), CMP-130/131/150/171/190 director/supervisor sign-off fields.
- **Edited** bespoke `⚡reference-lab-approval` (approver ← director) and `⚡safety-checklist` (tech supervisor ← profile).
- **New** Lab Profile page: `⚡lab-profile.blade.php` + `LabProfileController` + `resources/views/labs/profile.blade.php` + route `lab.profile` (`/labs/{lab}/profile`, `can:manage-lab-users`); "Lab Profile" link added to the dashboard header (managers).
- **New** `tests/Feature/LabProfileTest.php` (3). *(Undo: revert these + drop the `profile` column.)*

## 18. Invite users by email (2026-06-22)
- **Edited** `⚡user-management.blade.php` — added `inviteByEmail()` (pre-creates a `users` row by email — active, random password, `google_sub` null — + lab membership + default `tech_staff` role; idempotent for existing emails). The existing Google callback links them by email on first sign-in (no code change needed there). Added an "Invite by email" form, a success flash, and an "Invited · pending first sign-in" badge on members with no `google_sub`.
- **New** `tests/Feature/InviteUserTest.php` (4). *(Undo: revert the user-management edits + delete the test.)*

## 19. Real official-form filling via FPDI coordinate overlay — CMP-173 (2026-06-22)
Replaces the dompdf facsimile for CMP-173 with the **actual official template** filled by stamping
the captured answers onto the real blanks/checkboxes. Pipeline is generic; only CMP-173's map is done.
- **Installed** `setasign/fpdi` + `setasign/fpdf` (composer; edits `composer.json`/`composer.lock`, `vendor/`).
- **New** `app/Services/FormOverlayRenderer.php` — `supports($code)` (template+map present) + `render($formResponse)` (FPDI import + text/checklist stamping in PDF points).
- **New** `app/Forms/Overlays/CMP-173.php` — coordinate map (reference for the other 10 forms).
- **New** `resources/form-templates/CMP-173.pdf` — blank template pulled from Drive + normalized (committed asset).
- **New** `app/Console/Commands/PullFormTemplates.php` — `compliance:pull-templates [CODE]` (downloads `template_drive_id` per catalog form).
- **New** `scripts/normalize-pdf-templates.py` — re-saves Drive PDFs (1.5+/xref-streams) to an FPDI-readable form (PyMuPDF; offline asset-prep). Installed PyMuPDF via pip.
- **Edited** `app/Services/Drive/DriveClient.php` (+`GoogleDriveClient`, `tests/Support/FakeDriveClient.php`) — added `downloadFile($fileId)`.
- **Edited** `app/Forms/FormCatalog.php` — added `'template_drive_id'` to the CMP-173 entry.
- **Edited** `app/Services/FormService.php` — `fileToDrive()` now renders via a shared `renderPdf()` that prefers the overlay (falls back to dompdf `pdf_view`). Constructor gained `FormOverlayRenderer`.
- **Edited** `app/Http/Controllers/FormPdfController.php` — serves the overlay when supported, else dompdf.
- **New** `tests/Feature/FormOverlayTest.php`. **Suite: 93 → 96 passing.**

### 19b. CMS-209 federal form via AcroForm-assisted overlay (prototype, 2026-06-22)
- **New** `resources/form-templates/CMS-209.pdf` — official cms.gov CMS-209 fillable AcroForm, widgets flattened + normalized (PyMuPDF). Field name→rect extracted automatically (no manual measuring).
- **New** `app/Forms/Overlays/CMS-209.php` — Track-B reference map (text fields + `roster` block for the personnel rows).
- **Edited** `app/Services/FormOverlayRenderer.php` — added `stampRoster()` (repeating tables) + lab context (`lab_name`/`lab_clia`/`lab_address` loaded from the Lab).
- **Edited** `tests/Feature/FormOverlayTest.php` — CMS-209 supported + roster render test. **Suite: 96 → 97 passing.**
- Initial prototype filled lab identity + survey date + personnel names.
- *Undo:* delete the two new files, revert the renderer/test edits. (CMS-209 dompdf facsimile blade left as fallback.)

### 19c. CMS-209 completed + CMS-116 built — both federal forms done (2026-06-22)
- **Edited** `resources/views/components/⚡cms-209-report.blade.php` — `loadPeople()` now stores role KEYS per person (`people[].roles`) to drive the position checkboxes.
- **Edited** `app/Forms/Overlays/CMS-209.php` — added CLIA **position checkboxes** (role-driven: lab_director→LD, tech_supervisor→TS, general_supervisor→GS, tech_staff→TP) + printed director name + date (signature block).
- **New** `resources/form-templates/CMS-116.pdf` — official cms.gov CMS-116 fillable AcroForm (10 pages/297 fields), widgets flattened + normalized.
- **New** `app/Forms/Overlays/CMS-116.php` — multi-page map: identity + application-type/certificate-type checkboxes + effective date + total volume (p4) + printed director/date (p5).
- **Edited** `app/Services/FormOverlayRenderer.php` — added **multi-page stamping** (`page` on text/checks, default 1), a **value-driven checkbox** primitive (`checks`: stamp X when `key`==`equals`), role-driven roster check columns, and `lab_director` (profile) in context.
- **Edited** `tests/Feature/FormOverlayTest.php` — CMS-116 supported + multi-page render test; CMS-209 position test. **Suite: 96 → 98 passing.**
- *Undo:* delete `CMS-116.pdf`/`CMS-116.php`, revert the renderer/component/map/test edits.
- *Undo (whole feature 19):* delete the new files, revert the edits, `composer remove setasign/fpdi setasign/fpdf`. (Facsimile blades left in place as harmless fallbacks.)

### 20. Render Rightsize-owned forms in-app; overlay reserved for federal forms (2026-06-22)
The CMP-### forms are now Rightsize-owned SOPs (see the `Rightsize-SOPs/` manual), so the in-app PDF
is the official document — no overlay onto SLP's proprietary template. Overlay now applies ONLY to the
two federal CMS forms.
- **New** `resources/views/forms/_rsl-letterhead.blade.php` + `_rsl-footer.blade.php` — shared Rightsize letterhead (brand + SOP #) and footer (citation + © Rightsize + retention note). Border-proofed with inline `border:none`.
- **Edited** `resources/views/forms/{safety-checklist,generic,reference-lab-approval}-pdf.blade.php` — replaced the "Triad Behavioral Resources — Standard Operating Procedures" line with the shared letterhead + footer.
- **Edited** `app/Forms/FormCatalog.php` — added `SOP_META` (form code → `sop_code` + `citation`), merged in `get()`/`forObligation()`; **removed** CMP-173's `template_drive_id`.
- **Edited** `app/Services/FormOverlayRenderer.php` — `supports()` now restricted to `OFFICIAL_FEDERAL_FORMS = ['CMS-116','CMS-209']`; CMP forms fall through to native render.
- **Deleted** `resources/form-templates/CMP-173.pdf` + `app/Forms/Overlays/CMP-173.php` (SLP proprietary asset retired).
- **Edited** `tests/Feature/FormOverlayTest.php` — overlay-is-federal-only, owned-form SOP identity, native CMP render. **Suite: 98 passing.**
- *Undo:* revert the blade/catalog/renderer/test edits; (the deleted SLP CMP-173 template can be re-pulled from Drive if ever needed).

### 21. In-app forms for the last 5 obligations — all 16 now fillable in-app (2026-06-22)
Added native Rightsize forms for the log/numeric obligations that had an SOP but no in-app form.
- **New** `resources/views/components/⚡log-form.blade.php` — generic catalog-driven LOG form (header fields + a repeating add/remove data-entry table whose columns come from `FormCatalog['columns']`); stores `['fields'=>…, 'rows'=>[…]]`, advances the obligation. Dispatched via `component:'log-form'`.
- **New** `resources/views/forms/log-pdf.blade.php` — renders the log natively (Rightsize letterhead/footer + header fields + the column table).
- **Edited** `app/Http/Controllers/FormController.php` — accepts `form-wizard` OR `log-form`; **Edited** `resources/views/forms/generic.blade.php` — dispatches the right component.
- **Edited** `app/Forms/FormCatalog.php` — 5 new entries keyed by RSL code: RSL-DOC-100 (C06), RSL-EQ-100 (C07), RSL-EQ-110 (C08), RSL-HR-110 (C13), RSL-QC-110 (C14), each with `sop_code`/`citation`/`columns`. The Full Register "Fill" links light up automatically via `forObligation`.
- **New** `tests/Feature/LogFormTest.php` (3). Ran `npm run build`. **Suite: 98 → 101 passing.**
- *Undo:* delete the 2 new views + the test, revert the controller/generic/catalog edits.

### 22. Mirror SOP manual to Google Drive + master index (2026-06-22)
- **New** `RSL-000 Quality Management SOP Index.docx` (in `Rightsize-SOPs/`) — cover + master register mapping all 16 SOPs to obligation/cadence/citation. Generator: `_generator/build-index.js`.
- **New** `app/Console/Commands/MirrorSops.php` — `compliance:mirror-sops <source> [--parent=] [--folder=]`; find-or-create folder at the Drive root + upload each .docx (idempotent, skip-if-exists, never overwrites).
- **Edited** `app/Services/Drive/GoogleDriveClient.php` — added `uploadFile($folderId,$name,$contents,$mimeType)` (generic upload; uploadPdf unchanged).
- **Ran it (live):** created **Rightsize-SOPs** folder at the Shared Drive root (`18fXxFA4…`), folder id `1IH3NEnO7Cpz1hAYC_h4M6BC0ehXpaL3x` — all 17 .docx mirrored. The OneDrive copy remains the editing master.
- Deploy-readiness re-verified: `config:cache`/`route:cache`/`view:cache` all build (then cleared).
- *Undo:* delete the Drive folder; revert the command + client method.

### 23. Pre-prod cleanup (2026-06-22, night)
- **Drive cleanup (live):** trashed the stray test-filing artifacts — the `_App Write Test` folder + 14 obligation-coded `_signed.pdf`/`_signed.superseded_*` PDFs (created 2026-06-21/22). Real evidence (2026-06-17, `_signed_<date>` names) untouched. Recoverable in Drive trash ~30 days. New: `app/Console/Commands/DriveCleanup.php` (`compliance:drive-cleanup`, dry-run default, date-scoped) + `GoogleDriveClient::query()` + `trashFile()`.
- **De-SLP titles:** stripped the defunct " (CMP-###)" suffix from the 8 ex-SLP form titles in `FormCatalog` (federal "(CMS-116)/(CMS-209)" kept — real form numbers). Dashboard "Fill" link now shows the Rightsize SOP code (`sop_code ?? code`).
- **Pint:** formatted the session's new/changed files (style only).
- **Deploy-readiness:** 101 tests green; `config:cache`/`route:cache`/`view:cache` all build (no route closures); `.env.example` has every key present in `.env`.
- *Undo:* restore from Drive trash; `git`-less so revert the title regex / Fill-link / command by hand.

### 24. C17 — Annual IQCP review (2026-06-23)
Register expanded 16 → 17. IQCP (§493.1250) is the elective alternative to daily 2-level QC; labs that use it owe an annual IQCP review. Added on the user's "iqcp" go-ahead (assume the labs run IQCP — deactivate C17 per-lab if a lab runs daily 2-level QC instead).
- **Edited** `app/Services/LabProvisioner.php` — added C17 to `TEMPLATE` (Quality Control, annual/12 mo, signers Tech Supervisor + Lab Director); docblocks 16→17.
- **Edited** `app/Forms/FormCatalog.php` — new `form-wizard` entry `RSL-QC-120` (C17): risk-assessment + QC-plan checklist (6 items) + fields; carries `sop_code`/`citation` (§493.1250).
- **New** SOP `RSL-QC-120 Individualized Quality Control Plan (IQCP) Review.docx` (in `Rightsize-SOPs/`, generator `_generator/build-iqcp.js`). RSL-000 index regenerated (17 rows); both **mirrored to Drive** (old index trashed + re-uploaded).
- **Edited** count assertions 16→17 / 32→34 in `CompletenessReportTest`, `LabAdminTest`, `UserManagementTest`, + `TestCase`/provisioner comments; **New** `FormWizardTest::test_iqcp_review_form_completes_c17`. **Suite: 101 → 102 passing.**
- **Edited** `CLIA_CITATIONS.md` — IQCP moved from "gaps" to tracked C17. (Dev DB: run `migrate:fresh --seed` to pick up C17; prod seeds fresh with all 17.)
- *Undo:* remove the C17 TEMPLATE row + catalog entry, revert count assertions, delete the SOP + Drive copy.

### 25. Git + production deploy prep (2026-06-23)
- **git init** + first commit on `main` (`8580e1e`, 210 files). Secrets excluded: added `/.config` to `.gitignore` (the Drive service-account key) — verified `.env`, `.config/drive-sa.json`, `vendor`, `node_modules`, `public/build` all ignored and not staged.
- **New** `deploy/` artifacts (committed, non-secret): `RUNBOOK.md` (VPS + git + MySQL, IP-first then domain cutover), `env.production.example`, `nginx-rightsize.conf`.
- **Edited** `DEPLOY.md` §3 — corrected stale seeding (17-obligation register via `lab:create`; `db:seed` is dev-only demo data; added first super-admin bootstrap via tinker).
- Target deploy: plain VPS, git pull, MySQL (ready), Phase A on server IP (dev-login is `local`-only; Google OAuth needs a domain+https), Phase B = domain + SSL + Google SSO + SMTP.
- Next (user): create a PRIVATE remote, `git push -u origin main`, then follow `deploy/RUNBOOK.md` on the server.

## Docs updated (outside the project)
- `OneDrive/Documents/RSL/HANDOFF.md` and the project memory file — kept in sync with the above.
