# HemoPulse logic and duplication audit

Reviewed October 4, 2026. This is an application audit, not approval of clinical rules or a public deployment.

## Implementation result

The A1–A10 findings below describe the original audit. They have now been addressed: intake checks the effective waiting date under the donor lock; ended Pending/Confirmed records no longer block future booking and remain in the staff Overdue queue; status changes notify donors; original assessments are labelled history; dates and progress use shared state; campaign status/filter boundaries match slots; Deferred blood type is nullable; ledger provenance follows the unit intake; release packages include PHPMailer and tests.

Notifications now share ownership/pagination helpers. Workspace notices reuse the common notice renderer. Contact reply attempts are committed before SMTP with a stable request key. Sending/Unknown attempts must be reconciled against transport logs before a new attempt; retries do not resend the same attempt. An Accepted result means server acceptance, not verified inbox delivery.

Verification: 146 disposable integration checks pass, including the former audit probes. The release ZIP is also tested after extraction. Remaining manual/public-use checks are tracked in READINESS_GUIDE.md. No real email delivery has been verified.

## What currently works

- Donor signup requires terms and email-code verification; generated codes are absent from browser responses.
- Shared login applies throttling, regenerates sessions and checks active status and role on protected requests.
- Booking validates a current screening, waiting date, campaign capacity and generated appointment time. Donor/campaign locks protect duplicate booking and capacity updates.
- Pending -> Confirmed -> Checked-In -> Completed/Deferred is enforced by the backend. Duplicate donation intake is rejected.
- Completed intake updates appointment, history, donor dates, Pending inventory and intake transaction atomically.
- Untested inventory cannot move directly to Used. Expiration is detected once; unavailable units are excluded from available-stock totals.
- Cancellation, profile restrictions, public-reference searches, notification ownership, newsletter confirmation/unsubscribe, filtering and pagination have regression coverage.

## Original findings (addressed)

| ID / priority | Finding | Evidence / recommended correction |
| --- | --- | --- |
| A1 / High | An existing booking can receive a Completed donation while the latest screening has an active dated deferral. | Probe: book, confirm, defer screening, check in, submit Completed -> HTTP 201. `includes/api_records.php` checks Checked-In and duplicate intake but does not revalidate the active interval/deferral. Apply the same waiting rules at final intake. If overrides are a required business feature, define explicit authorization, reason and audit requirements first. |
| A2 / High | The release ZIP omits the SMTP dependency. | `scripts/package.ps1` lists folders without `vendor`; `includes/mailer.php` requires three PHPMailer files. A packaged copy will fail SMTP when those files are absent. Include vendor and its deny rule; test an extracted release independently. The package also omits the tests/test_runner referred to by its documentation. |
| A3 / Medium | An unresolved past Pending appointment prevents a new booking. | Probe: past Pending appointment + current eligible screening + new open campaign -> HTTP 422 active-registration error. Staff cancellation resolves it, so this is not an irreversible lock. Add an explicit overdue-registration queue and terminal resolution policy; do not silently discard history. |
| A4 / Medium | Confirming a booking produces no persistent donor notification. | Probe: confirmation succeeds but donor notification count does not change. `updateAppointment()` writes an audit entry only. Add donor updates for confirmation, cancellation and relevant attendance changes, with duplicate protection. |
| A5 / Medium | Approved screening still displays unresolved original review wording. | Probe: medication-related Needs Review -> staff Eligible approval; the checker displays the approval and “Medication requires staff review.” `includes/eligibility_panel.php` always renders original `reasons_json`. Keep original reasons as labelled history and use the reviewed decision for current guidance. |
| A6 / Medium | Two return dates can be displayed on one page. | After A1, the progress summary displayed the new three-month date while screening detail still said to return on the earlier deferral date. `donorNextDate()` and `screening.deferred_until` independently feed the UI. Display one effective next date and label any historical decision date separately. |
| A7 / Medium | Waiting-period progress mixes historical completion and an incomplete new cycle. | Probe and browser: first four steps incomplete, Donation and History complete. `includes/progress_state.php` takes the first four steps from active registration and last two from prior history. Show the completed journey separately from the next-cycle status. |
| A8 / Medium | Campaign can be OPEN with no selectable appointment time. | Fixed-time probe: 08:00-16:00 event, 30-minute slots, current time 15:45 -> OPEN but empty slot list. `campaignRegistrationStatus()` checks end/deadline/capacity; `campaignSlots()` filters past slots separately. Make booking availability use both conditions, including equivalent API filter logic. |
| A9 / Medium | Deferred intake requires a blood type although nothing was collected. | Probe: Deferred with reason/date and no blood type -> HTTP 422. Backend and workspace require the value; schema is NOT NULL. Support an explicit unknown/not-collected value or nullable blood type for Deferred outcomes. Require a measured type for Completed intake. Use an additive migration. |
| A10 / Medium | Later inventory transactions lose donor/campaign provenance. | Probe: the release Adjustment transaction for a donation unit has empty donor/campaign fields. API joins provenance through each transaction's `donation_id`; status-change entries only carry `inventory_id`. Resolve provenance through the unit's intake relationship. Unknown legacy origins should remain labelled unknown. |

These are ten listed findings because A2 is a packaging finding from static review; nine are reproduced by the logic probes.

## Remaining operational checks

Date-based attendance checks permit No-Show on the event date before the scheduled appointment time and permit historical check-in. Define whether late record entry is supported, how its actual donation date is recorded, and when No-Show becomes valid. This is a business-rule review item, not a claim that staff made an incorrect decision.

SMTP inbox delivery, backup restoration, production permissions/HTTPS, upload rendering across devices and complete accessibility testing remain unverified. Password recovery and sending announcement newsletters remain unimplemented features. Newsletter subscription management alone does not send campaigns to the mailing list.

## Duplicates and overlaps

| Area | What repeats | Assessment |
| --- | --- | --- |
| Script imports | Six main pages checked: index, contact, locations, helpdesk, dashboard, workspace | No duplicate script imports found. |
| Workflow state | `progress_state.php` computes state; `donation_progress.php` independently recalculates screened, confirmed, waiting and completed history | Consolidate into a richer shared state result; A6/A7 show why this matters. |
| Campaign availability | PHP status helper, generated-slot helper and SQL CASE in `listCampaigns()` | Behavior must match across cards, filters and booking. A8 exposes drift. |
| Notifications | Server-rendered panel/form handler and separate API GET/PUT | Both enforce ownership, but the panel paginates while API GET stops at 50. Share service functions to keep count/read/paging semantics aligned. |
| Temporary notices | `js/notices.js` and local `notice()` in workspace | Similar creation, dismiss and timeout logic; consolidation reduces drift. |
| Form state | `form-guard.js`, modal validation and donor registration validation | Multiple handlers update buttons. No stuck-button failure was established in this audit. Share button-state ownership and test retries before removing any handler. |
| CSS | Newsletter rules in style/design/app; search controls in locations/design; app-notice rules in app/design | Some layering is intentional, but repeated component rules make cascade order significant. Consolidate per component and verify desktop/mobile before deleting overrides. |
| Compatibility endpoints | `backend/api/*` and `api/index.php` | They are adapters, not all duplicate business logic. Booking already delegates to the shared service. Campaign/inventory list responses differ. Identify consumers before removing routes. |
| Visible campaign content | Upcoming cards, filtered campaign cards and schedule table | Same campaigns appear in multiple presentations. Upcoming cards stay outside the filtered result list. Clarify filter scope or simplify the page. |
| Documentation | Old mail-only instructions versus newer SMTP guide; older checklist test counts | Link one current mail configuration guide and label older verification records by date. Do not follow stale “SMTP unsupported” statements. |

Browser and server validation of the same fields is intentional: client validation improves usability, while server validation remains authoritative. SQL migrations that expand older base tables are also intentional, not duplicate live data.

## Reproduce

```powershell
Set-Location C:\xampp\htdocs\hemopulse
& C:\xampp\php\php.exe tests\integration.php --audit
```

Start XAMPP MySQL first. The suite requires permission to create/drop its randomly named test database. `132 checks passed` describes regression checks only. Read all subsequent `GAP:` lines: the audit observations do not make the process exit nonzero merely because a known gap is present.

Use `--browser` as well for a temporary fixture. It prints the URL, fixture-only credentials and a stop-file path. Close the fixture by creating that stop file or let it expire. See `READINESS_GUIDE.md` for manual acceptance steps and release gates.
