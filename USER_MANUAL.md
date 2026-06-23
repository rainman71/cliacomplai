# Rightsize CLIA Compliance — User Manual

A guide for lab staff and directors using the Rightsize CLIA Compliance app.

The app answers three questions, continuously and per lab: **what's due and when**, **who still
needs to sign**, and **is every required document on file** — sitting on top of your existing
Google Drive records. It stores **no patient data** — only compliance metadata, dates, and links
to the documents in Drive.

---

## Contents
1. [Getting started — signing in](#1-getting-started--signing-in)
2. [Your labs (the Portfolio)](#2-your-labs-the-portfolio)
3. [The lab dashboard](#3-the-lab-dashboard)
4. [Understanding statuses](#4-understanding-statuses)
5. [Completing an obligation](#5-completing-an-obligation)
6. [Filling official forms](#6-filling-official-forms)
7. [Signatures](#7-signatures)
8. [Reports](#8-reports)
9. [Working across multiple labs](#9-working-across-multiple-labs)
10. [Email reminders](#10-email-reminders)
11. [Google Drive — auto-filing & auto-sync](#11-google-drive--auto-filing--auto-sync)
12. [For administrators](#12-for-administrators)
13. [Reference: roles & permissions](#13-reference-roles--permissions)
14. [Reference: the 16 obligations](#14-reference-the-16-obligations)
15. [FAQ & troubleshooting](#15-faq--troubleshooting)

---

## 1. Getting started — signing in

1. Open the app URL. You'll be sent to the **Sign in** page.
2. Click **Sign in with Google** and choose your work Google account.
3. The first time you sign in, your account is created in a **pending** state with **no lab
   access**. An administrator must grant you access to a lab and assign your role(s) before you
   can do anything. If you see a "no access" message, contact your compliance administrator.
4. Once you have access, signing in takes you to your **Portfolio** (or straight into your lab if
   you only belong to one).

**Signing out:** use the **Sign out** link in the top-right header.

---

## 2. Your labs (the Portfolio)

The **Portfolio** (the landing page) shows a card for every lab you have access to, with that
lab's at-a-glance counts: **Overdue**, **Due soon**, **On track**, and **Set dates**.

- Click a lab card to open that lab's dashboard.
- If you belong to only one lab, you skip the portfolio and land directly in it.
- A **lab switcher** in the dashboard header lets you jump between labs.

---

## 3. The lab dashboard

Inside a lab, the dashboard is organized into tabs:

| Tab | What it shows |
|-----|---------------|
| **Overview** | Summary counts and highlights for the lab. |
| **Due Soon** | Obligations coming due within ~60 days, soonest first. |
| **Overdue** | Everything past its due date, most overdue first. |
| **Full Register** | The complete list of all 16 obligations — and where you edit them. |
| **Awaiting Signature** | Items currently out for signature, with per-signer status. |
| **Completeness** | Are all required documents on file? Plus CSV/PDF export. |
| **Activity** | An audit trail of every change, newest first. |

The **Full Register** is the heart of the app — each row is one obligation (C01–C16) showing its
code, name, owner role, frequency, last-completed date, next-due date, days remaining, status,
signature status, document link, and notes.

---

## 4. Understanding statuses

Every obligation gets one status, calculated from its **last-completed date** + its **frequency**:

| Status | Meaning |
|--------|---------|
| 🔴 **Overdue** | Past its due date. |
| 🟠 **Due ≤30** | Due within 30 days. |
| 🟡 **Due ≤60** | Due within 60 days. |
| 🟢 **On track** | More than 60 days out. |
| ⚪ **Set dates** | No baseline date yet — needs a last-completed date to start tracking. |

**"⚠ not verified · N d" badge:** a small amber note may appear on a register row when an
obligation hasn't been touched in the app for longer than its review window. It's a nudge that the
status may be stale — worth confirming the record still reflects reality, even if it shows green.

---

## 5. Completing an obligation

There are three ways an obligation gets marked complete (its date advances and its next-due
recalculates automatically):

**a) Edit the Full Register directly.** In the register, set the **Last Completed** date, paste a
**Document link** (Drive URL), choose a **Signature status**, or add **Notes**. Changes
**auto-save** when you click away from the field, and every edit is written to the Activity log.
*(Editing requires an editor role — see §13.)*

**b) Fill an official form** (recommended where available). See §6.

**c) Auto-sync from Drive.** When you file signed evidence into the lab's Drive folder, the app can
pick it up automatically and advance the obligation. See §11.

---

## 6. Filling official forms

For many obligations you can complete the actual compliance form **inside the app** — answer the
questions, and the app stores the values, generates the completed **PDF**, files it, and marks the
obligation complete in one step (no separate paperwork or manual date entry).

**To fill a form:** in the **Full Register**, look for the green **"Fill …" link** on the row
(e.g. *Fill CMP-173 →* on the Lab safety check row). Click it, answer the questions, then
**Complete & file**. You'll get a link to download the completed PDF.

Many fields arrive **pre-filled from the Lab Profile** (Laboratory Director, supervisors, CLIA
info, hours, etc.) — set those once on the **Lab Profile** page (see section 12) and every form
picks them up, so you're confirming rather than retyping.

Forms available today, by obligation:

| Obligation | Form | What you fill in |
|---|---|---|
| C01 CLIA certificate | CMS-116 | Application fields (mostly pre-filled from the lab profile) |
| C02 Proficiency testing | CMP-150 | PT survey checklist |
| C03 Alternative assessment | CMP-190 | Alternate PT attestation |
| C04 LD on-site visit | CMP-132 | On-site visit checklist |
| C05 Personnel competency | CMS-209 | Personnel roster (pre-filled from your assigned roles) |
| C09 Patient result approval | CMP-133 | Monthly remote monitoring checklist |
| C10 Reference lab approval | CMP-172 | Reference-lab list + director sign-off |
| C11 Lab safety check | CMP-173 | Safety checklist (yes/no items) |
| C12 QC review | CMP-130 | Monthly QC review & sign-off |
| C15 New/modified test | CMP-171 | New/changed-test request |
| C16 QA program review | CMP-131 | Quality-assurance monitor checklist |

The remaining obligations (C06, C07, C08, C13, C14) are completed by editing the register or via
Drive sync.

---

## 7. Signatures

Some obligations require one or more people to sign off. The workflow:

1. In the **Full Register**, click **Send for signature** on the obligation's row. It moves to the
   **Awaiting Signature** tab.
2. On the **Awaiting Signature** tab, each required signer is listed. As people sign, mark each
   signer **Signed** (or **Reject** if it needs rework).
3. When **all** required signers have signed, click **Mark complete & file** — this records the
   completion, advances the due date, and files the evidence to Drive.

You'll see **reminder flags** for items pending 5 days (a nudge) and 10 days (an escalation).

---

## 8. Reports

**Completeness report** (Completeness tab): shows every obligation and whether it has a current
date and document on file. Export with:
- **Export CSV** — for spreadsheets.
- **Export PDF** — a clean, dated report suitable for an inspection binder.

Reports are scoped to the lab you're in.

---

## 9. Working across multiple labs

If you oversee several labs (e.g. a lab director):

- **Portfolio** — per-lab counts at a glance (§2).
- **Across All Labs** (executive roll-up) — overdue obligations grouped by lab, with a CSV export.
- **Overdue Worklist** — one flat, prioritized list of *everything overdue across all your labs*,
  most-overdue first. The fastest "what do I deal with first this morning" view. (Linked from the
  Across All Labs page.)

---

## 10. Email reminders

The app emails the right people automatically (recipients are resolved from each obligation's
owner/role within that lab):

- **Due-date ladder** — reminders at **30, 7, and 0 days** before due, and **1 day overdue**.
- **Signature reminders** — a **5-day** nudge and a **10-day** escalation for items awaiting
  signature.
- **Weekly overdue digest** — a Monday summary of everything overdue.

No action needed to enable these — they run on a daily schedule.

---

## 11. Google Drive — auto-filing & auto-sync

The app works on top of your lab's Google Drive, where the real signed documents live.

- **Auto-filing:** when you complete a form or finalize a signature in the app, the generated PDF
  is filed into the lab's Drive folder tree, named by convention. Re-filing the same item never
  overwrites — a prior version is moved into an **`Archived/`** subfolder and the new one becomes
  current.
- **Auto-sync (daily):** each morning the app scans the lab's Drive folder for newly **signed**
  evidence (files named `…_signed_YYYY.MM.DD.pdf`), matches each to its obligation, and advances
  the obligation automatically — so filing a signed document in Drive keeps the register current
  without anyone typing a date. Each obligation's document link then points straight at the signed
  PDF in Drive.

Your administrator configures which Drive folder each lab uses (see §12).

---

## 12. For administrators

### Managing users & access (per lab)
Managers (Admin, Lab Director, Compliance Specialist) see a **Users** link in the dashboard header.
On the Users & Access page you can:
- **Add** an existing user to the lab.
- **Assign one or more roles** per person (click a role chip to toggle it — a person can hold
  several roles in one lab, e.g. Lab Director *and* Technical Supervisor).
- **Grant or revoke** a person's access to the lab.
- **Invite by email** — for someone who hasn't signed in yet: enter their email (and optional name)
  and they're pre-registered. They get access (Technical Staff to start) the **moment they first
  sign in with Google**, and show as "Invited · pending first sign-in" until then.

Two ways to add people: pick from **existing users** (anyone who has signed in before), or
**Invite by email** for brand-new people. New members start as **Technical Staff (read-only)** and
**active**; adjust their roles here.
You can't remove your own last management role from a lab (a safety guard).

### Managing labs (super admin)
Super admins see a **Manage Labs** option to:
- **Create** a new lab (also available via the `lab:create` command), which clones the standard
  16-obligation register into it.
- **Activate / deactivate** a lab.
- Set each lab's **CLIA number** and **Drive root folder** (the folder shared with the service
  account that the app files into and scans).

### Lab Profile (auto-fills forms)
Managers see a **Lab Profile** link in the dashboard header. Fill in the lab's CLIA #, address,
Laboratory Director, technical/general supervisors, phone, hours of operation, CLIA certificate type,
estimated annual test volume, and specialties. These values **pre-fill the in-app forms** — CMS-116
and the director/supervisor sign-off fields across the other forms — so you confirm rather than
retype. Set it once; every form picks it up. You can still edit any field while filling a form.

### Cadences per lab
The 16 obligations come with standard CLIA cadences, but you can adjust an obligation's frequency
to match a specific lab's certificate or PT enrollment. The regulatory basis for each cadence is
documented in `CLIA_CITATIONS.md`.

---

## 13. Reference: roles & permissions

Roles are assigned **per lab** — a person can have different roles in different labs, and several
roles in one lab.

| Role | Can manage users & labs | Can edit the register | Read-only |
|------|:---:|:---:|:---:|
| Admin | ✓ | ✓ | |
| Lab Director | ✓ | ✓ | |
| Compliance Specialist | ✓ | ✓ | |
| Technical Supervisor | | ✓ | |
| General Supervisor | | ✓ | |
| Safety Officer | | ✓ | |
| Technical Staff | | | ✓ (view only) |

- **Super admin** (Rightsize HQ) sees and manages every lab.
- Read-only users see the full dashboard and reports but can't change data; edit controls are
  hidden and a "· read-only" note appears in the header.

---

## 14. Reference: the 16 obligations

| Code | Obligation | Standard frequency |
|------|-----------|--------------------|
| C01 | CLIA Certificate of Compliance renewal (CMS-116) | Every 2 years |
| C02 | Proficiency testing — regulated analytes (AAB) | 3 events / year |
| C03 | Alternative assessment — non-PT analytes (LCMS, ETOH) | ≥ 2 / year |
| C04 | Lab Director on-site visit | 2 / year (≥ every 6 mo) |
| C05 | Personnel competency assessment | Annual (semiannual in year 1) |
| C06 | Procedure / SOP review | Annual |
| C07 | Equipment calibration & verification | At least every 6 months |
| C08 | Pipette verification | Annual |
| C09 | Patient result approval | Monthly |
| C10 | Reference lab approval (designated referral) | Annual |
| C11 | Lab safety check | Annual |
| C12 | QC review (Levey-Jennings) | Monthly |
| C13 | Personnel licenses & credentials | Per credential expiry |
| C14 | Comparison of test results (inter-instrument) | Twice a year |
| C15 | New/modified test performance verification | When a test is added/changed |
| C16 | Quality assessment program review | Annual |

Cadences can be tailored per lab. See `CLIA_CITATIONS.md` for the 42 CFR Part 493 citation behind
each one.

---

## 15. FAQ & troubleshooting

**I signed in but can't see anything.** Your account is pending — an administrator needs to grant
you lab access and a role. Contact your compliance administrator.

**I can see the dashboard but can't edit.** You have a read-only role (Technical Staff). Ask an
admin to assign an editor role for that lab.

**An obligation shows "Set dates."** It has no baseline last-completed date yet. Set one in the
Full Register, fill its form, or file signed evidence in Drive and let the daily sync pick it up.

**An item is green but says "⚠ not verified."** The data hasn't been confirmed in a while — it's a
prompt to double-check the record is still accurate.

**I filed a document in Drive but the app didn't update.** The daily Drive sync runs each morning;
make sure the file is named with the `…_signed_YYYY.MM.DD.pdf` convention and lives in the lab's
configured Drive folder. An administrator can confirm the Drive folder setup.

**Does the app store patient information?** No. It stores only compliance metadata, dates, and
links. The actual documents live in Google Drive.

**Who gets the reminder emails?** The people whose role matches the obligation's owner within that
lab. Make sure roles are assigned correctly on the Users & Access page.
