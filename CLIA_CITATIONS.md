# CLIA Citations — Obligation Cadences & Regulatory Basis

Maps each obligation in the register (the `LabProvisioner::TEMPLATE`) to its governing
authority under **42 CFR Part 493** (and OSHA where noted), with the verified cadence.
Verified 2026-06-21 against primary text (eCFR / Cornell LII). Use this when adjusting a
lab's cadences per its certificate, test menu, and accreditation program.

> **CLIA vs. accreditation/lab policy.** Several cadences below are stricter than CLIA's
> floor because they follow CAP/COLA convention or the lab's own SOP. That is fine and
> common — just know which intervals are federally mandated vs. internal policy when a lab
> asks to change one.

## Confirmed cadences

| # | Obligation | Cadence | Authority | Mandated by CLIA? |
|---|---|---|---|---|
| C01 | CLIA Certificate of Compliance renewal | 24 mo | Part 493 Subpart F (cert valid 2 yrs; biennial survey) | ✅ Yes |
| C02 | PT — regulated analytes (AAB) | 4 mo (3/yr) | §493.801 — 3 PT events/yr per analyte | ✅ Yes |
| C03 | Alternative assessment (LCMS, ETOH) | 6 mo (2/yr) | §493.1236(c)(1) — verify accuracy ≥ twice/yr for analytes with no PT program | ✅ Yes |
| C04 | Lab Director on-site visit | 6 mo (2/yr) | §493.1445 — onsite ≥ every 6 mo, ≥4 mo between the 2 visits | ✅ Yes |
| C05 | Personnel competency assessment | 12 mo | §493.1235, §493.1451(b)(8) — semiannual in yr 1, then annual | ✅ Yes (see note) |
| C06 | Procedure / SOP review | 12 mo | §493.1251 — **no interval**; approve/sign/date at adoption + on change | ❌ No — annual is CAP/lab policy |
| C07 | Equipment calibration & verification | 6 mo | §493.1255 — ≥ every 6 mo + on reagent-lot change / major maint | ✅ Yes |
| C08 | Pipette verification | 12 mo | §493.1254/1255 — manufacturer/lab-defined function checks | ❌ No — manufacturer/lab policy |
| C09 | Patient result approval | 1 mo | §493.1289, §493.1291 — review mechanism; no fixed interval | ❌ No — monthly is lab policy |
| C10 | Reference lab approval (referral) | 12 mo | §493.1242 — must refer only to CLIA-certified labs; annual reapproval = accreditation practice | ❌ No — lab/accreditation policy |
| C11 | Lab safety check | 12 mo | OSHA §1910.1450 (not CLIA); lab SOP (CMP-173) = annual | ❌ No — OSHA + lab SOP |
| C12 | QC review (Levey-Jennings) | 1 mo | §493.1256 — QC ≥ each day of testing; monthly review = documented sign-off | ✅ Daily QC mandated; monthly review is the sign-off |
| C13 | Personnel licenses & credentials | event | §493.1351+ qualifications; NC does not license lab personnel (degree-based) | ✅ (event-driven) |

**C05 note:** CLIA requires competency **semiannually during an employee's first year**, then
annually. The template models a single 12-month interval and does not capture the first-year
semiannual cadence for new testing personnel.

## Gaps — CLIA obligations not (fully) in the register

1. **Comparison of test results — §493.1281 (twice/year).** Required when the *same analyte*
   is reported from **two or more instruments/methods** (or sites). Applies to a lab only if its
   instrument setup creates such overlap (e.g., immunoassay screen + LCMS confirmation reporting
   the same drug). **Conditional — confirm against the lab's equipment.**
2. **Establishment / verification of performance specifications — §493.1253 (event-driven).**
   Before reporting any new or modified non-waived test, verify accuracy, precision, reportable
   range, and reference intervals. Not currently tracked.
3. **Quality Assessment program — §493.1239 / 1249 / 1289 / 1299.** Ongoing QA mechanism across
   pre-analytic / analytic / post-analytic phases (commonly an annual QA program review). The
   register touches pieces (C09, C12) but has no holistic QA-review obligation.
4. **IQCP status — §493.1250.** ✅ Now tracked as **C17 — Annual IQCP review** (added 2026-06-23, annual).
   Applies only to labs running an Individualized Quality Control Plan instead of daily 2-level external
   QC; **deactivate C17 for any lab that runs daily 2-level QC** (covered by C12). Each test system's IQCP
   (risk assessment + QC plan) is reviewed at least annually and after failures/changes.

**Out of scope by design:** daily QC and temperature/environmental logs (e.g., CMP-113/114/115)
are continuous logbooks, not periodic sign-off obligations, so they are not tracked in the register.

## Sources
- [42 CFR Part 493 (eCFR)](https://www.ecfr.gov/current/title-42/chapter-IV/subchapter-G/part-493)
- §493.801 PT enrollment — https://www.law.cornell.edu/cfr/text/42/493.801
- §493.1236 alternative assessment — https://www.law.cornell.edu/cfr/text/42/493.1236
- §493.1235 competency — https://www.law.cornell.edu/cfr/text/42/493.1235
- §493.1451 technical supervisor — https://www.law.cornell.edu/cfr/text/42/493.1451
- §493.1251 procedure manual — https://www.law.cornell.edu/cfr/text/42/493.1251
- §493.1253 performance specifications — https://www.law.cornell.edu/cfr/text/42/493.1253
- §493.1255 calibration & verification — https://www.law.cornell.edu/cfr/text/42/493.1255
- §493.1256 control procedures (QC) — https://www.law.cornell.edu/cfr/text/42/493.1256
- §493.1281 comparison of test results — https://www.law.cornell.edu/cfr/text/42/493.1281
- §493.1445 director responsibilities — https://www.law.cornell.edu/cfr/text/42/493.1445
- §493.1289 analytic systems QA — https://www.law.cornell.edu/cfr/text/42/493.1289
- IQCP (CMS) — https://www.cms.gov/Regulations-and-Guidance/Legislation/CLIA/Individualized_Quality_Control_Plan_IQCP.html
