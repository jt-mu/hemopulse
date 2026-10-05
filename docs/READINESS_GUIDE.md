# HemoPulse website readiness guide

Updated October 4, 2026. Use this checklist after changes and before a demonstration or release. Record Pass / Fail / Not tested, the date, tester, browser and supporting evidence for each row. “Not tested” is not a pass.

Current result: 190 integration checks pass, including the reproduced logic gaps. See LOGIC_AUDIT.md for implemented corrections. Complete the manual and production checks below before declaring public-use readiness.

## 1. Choose the environment

For the installed site, start Apache and MySQL in XAMPP and visit `http://localhost/hemopulse/index.php`. Public-page inspection is safe; use the disposable fixture for account, registration, donation and inventory scenarios.

Open PowerShell:

```powershell
Set-Location C:\xampp\htdocs\hemopulse
& C:\xampp\php\php.exe tests\integration.php --audit --browser
```

The test suite creates a separate database and forces mail previews. Read its output, then use the printed fixture URL. The printed administrator/staff credentials belong only to that temporary database. It expires after ten minutes; creating the printed stop file cleans it up earlier. Do not change the computer clock to simulate attendance. Create an event dated today inside the fixture and use an appointment appropriate to the test.

Preview-mode signup requires retrieving the code from the fixture's private preview directory. Its parent directory is shown in the printed stop-file path; previews are in its `mail` subfolder. Treat previews as sensitive and keep them outside public web access. A preview validates message generation, not real delivery.

For a release test, extract the generated package into a separate directory and use a separate database. Do not import `deployment/schema.sql` over an existing database. Run migration on the test installation and verify PHPMailer files exist in `vendor/phpmailer/src`; the packaging script includes them.

## 2. Run automated checks

| Check | Expected result |
| --- | --- |
| `php tests/integration.php` | All regression checks pass; no uncaught failures. |
| `php tests/integration.php --audit` | Read each GAP line separately. Resolve or document each finding; a zero process exit does not mean the audit found no gaps. |
| `php -l` for modified PHP files | No syntax errors. |
| Node `--check` for modified JavaScript | No syntax errors. |
| Migration on fresh test DB, then rerun migration | Both succeed; existing test records and references remain intact. |

The latest audit checked 70 PHP files and 10 JavaScript files. New files or edits require new checks. Automated endpoint coverage does not replace the browser exercises below.

## 3. Exercise visitor and account flows

| Action | Expected result |
| --- | --- |
| Open Home, Locations, Contact and System FAQs | Correct titles, loaded images, working navigation and readable footer. |
| Click Donate while signed out | Login opens; successful donor login opens the dashboard. |
| Register with missing terms, invalid email or weak password | Submission is rejected with useful feedback. No account is created. |
| Register with valid details; enter wrong, expired and then valid test codes | Wrong/expired codes fail; valid unused code creates one donor account. Code is absent from browser responses and console. |
| Repeat login with wrong password, then correct password within allowed limits | Useful error; button recovers. Success creates an authenticated session. |
| Log out, refresh a protected page, then use Back | Protected data cannot be used without authentication. |
| Open staff/admin URLs as a donor; open Users as staff | Access denied by the server. |
| Deactivate a test account as admin; use its existing session | Next protected request refuses the account. |
| Upload valid picture, oversized file and non-image as donor/staff | Valid image renders; invalid files fail. Protected identity/role cannot be edited through the profile form. |

Also try keyboard-only operation: Tab reaches controls in sensible order, focus is visible, Enter submits valid forms, Escape closes dialogs where supported, and focus returns to a usable control.

## 4. Exercise the donor journey

| Action | Expected result / current audit note |
| --- | --- |
| Browse a campaign before screening | Campaign browsing does not complete eligibility or create a registration. |
| Submit an eligible screening | Current result unlocks registration. |
| Submit a Needs Review screening, then approve it as staff | Registration unlocks and staff decision becomes current guidance. Original warnings must be clearly historical; currently A5 fails this. |
| Save a campaign choice; sign in in a new session | Saved choice restores for that screening. |
| Book a future generated slot; try duplicate booking | First booking creates one Pending registration and reduces capacity once; duplicate fails. |
| Confirm as staff; inspect donor history and Notifications | Status is Confirmed; notification should appear. Currently A4 fails the notification part. |
| Cancel a Pending/Confirmed future booking with a reason | Cancellation persists and capacity is restored once. Repeat cancellation must not restore another slot. |
| Leave a past appointment unresolved; attempt new registration | Website should explain and support staff resolution. Currently A3 requires manual staff cleanup. |
| Try event closing time or a past time | Booking fails. A campaign with no remaining generated slot should not be labelled bookable; currently A8 fails that label. |
| Check in, then record Completed once | Appointment becomes Completed; history, next date, one Pending unit and one intake transaction appear. Repeat intake is rejected. |
| Put the booked donor under an active dated deferral, then attempt Completed | Final intake must enforce the agreed waiting rule or an explicit authorized override policy. Currently A1 accepts Completed without an override. |
| Record Deferred without collection | Reason and review date required; no inventory added. Blood type should support unknown/not collected; currently A9 requires a value. |
| Inspect waiting-period eligibility page | One effective next date and a clearly identified completed journey/new cycle. Current A6/A7 can show contradictory dates/steps. |
| Open My Account/My Donations | No repeated workflow tracker or unrelated notification block. |

Use distinct donors for Completed and Deferred scenarios so their histories are easy to compare.

## 5. Exercise staff/admin operations

| Action | Expected result |
| --- | --- |
| Publish/edit a future campaign; combine keyword, venue, status and date filters | Cards and schedule match filter scope; paging preserves parameters. Upcoming previews are currently separate from filters. |
| Delete an unbooked campaign with a saved selection | Campaign and unsubmitted selection disappear together. |
| Delete a campaign with registration history | Rejected; closing remains available. |
| Reschedule Pending/Confirmed bookings into compatible hours | Existing times remain valid and donors receive reschedule updates. Incompatible slots/history block the edit. |
| Review the same donor's older screening | Rejected when a newer screening exists. |
| Change inventory Pending -> Used | Rejected. Pending -> Available requires staff processing notes. |
| Change Available -> Reserved -> Available/Used/Discarded | Only allowed transitions succeed; terminal states cannot reopen. |
| Access an expired unit twice | Unit is unavailable and exactly one automatic expiration transaction is added. |
| Inspect intake and later processing transactions | Reference, responsible person, full notes and unit origin are understandable. Currently A10 loses donor/campaign on later changes. |
| Search enough records for multiple pages | Accurate total, distinct page records, Previous disabled on first page, Next disabled on last. |
| Read and mark notifications; try another donor's notification ID | Own unread state persists; another user's state does not change. Older pages remain accessible. |
| Compare dashboard metrics with the fixture records; export CSV | Counts match the stated event-date basis; CSV opens safely and includes the expected data. Operational stock metrics are current, not all filtered by report dates. |

## 6. Exercise contact and newsletter

1. Submit a contact inquiry. It appears as New for staff. Read, reply and close it; full reply history retains staff/time/delivery status.
2. In preview mode, the reply remains a preview and the site says no email was sent. Do not treat this as an inbox test.
3. Subscribe once and repeat the request. Only one email subscription row should exist; throttle rules should prevent rapid repeated sends.
4. Open the confirmation link and explicitly confirm. Status becomes Subscribed. Repeat/expired confirmation must not create another row.
5. Open unsubscribe and confirm. Status becomes Unsubscribed. Re-subscription must require a fresh confirmation.
6. Confirm admin can view subscribers and staff/donor cannot access the management list.
7. For real delivery, configure the Apache process with `MAIL_MODE=smtp`, SMTP host/port/security/username/password, authorized `HEMOPULSE_MAIL_FROM`, monitored `MAIL_REPLY_TO` and reachable `HEMOPULSE_APP_URL`. Restart Apache and test only mailboxes you control. See `PDF_REVISIONS.md` for full configuration.
8. Verify actual inbox arrival for OTP, newsletter confirmation/unsubscribe links and contact replies. Test transport failure and a retry. Record server acceptance separately from inbox delivery.

Announcement/newsletter broadcasting is not implemented. Subscription confirmation is the feature currently available. Avoid manually retrying an uncertain contact send without checking delivery: SMTP acceptance cannot be rolled back with the database.

## 7. Check browser, layout and failure recovery

- Open Developer Tools (F12), Console and Network. Refresh each main page and exercise forms. Record unexpected errors, 404 assets, 500 responses and unreadable API JSON. Intentional 401/403/422 responses in negative tests are expected.
- Test representative widths: 1440, 1024, 768 and 390 pixels. Look for clipped labels, overlapping close controls, inaccessible navigation, stretched images and table overflow outside its scroll container.
- Test long names, references, notes and message bodies. Text must wrap or expand safely, not execute HTML.
- Test an incorrect form/server response, dismiss the alert, then retry. Buttons recover and old success messages do not stay on unrelated views.
- In the isolated environment, simulate unavailable services. Show a friendly fallback and prevent unauthorized or partial operations. Restore the service and verify recovery.
- Check a second browser and keyboard navigation. If assistive technology is part of acceptance, test it explicitly; do not infer support from screenshots.

## 8. Check release and recovery readiness

| Item | Evidence required before public deployment |
| --- | --- |
| Extracted release | Fresh installation works from the package alone, including SMTP dependencies and protected folders. |
| HTTPS/session configuration | Session cookie has Secure/HttpOnly/SameSite; logout and timeout behave on the real host. |
| Database access | Dedicated runtime account; schema migration privileges handled separately; no default root/blank-password production setup. |
| Private resources | Requests for config, includes, SQL, tests, scripts, docs, vendor, logs, previews and stored uploads cannot expose private content. Test the actual web server, including its development router if used. |
| Error logging | Generic user messages; private, useful logs without passwords, codes or SMTP credentials. |
| Backup and restore | Export DB and preserve private profile-photo storage/configuration separately. Restore into a separate installation, then verify history, references and photos. A backup file alone is not evidence of recovery. |
| Email | Actual receipt and working links on the deployed URL; monitored Reply-To. |
| Operational rules | Overdue appointments, no-show timing, late entry, deferral overrides and clinical correction procedures are explicitly agreed and tested. |

## 9. Decide readiness

- **Demonstration readiness:** the required visitor/donor/staff/admin script completes in the test environment, displayed states are coherent, known limitations are stated, and no priority issue undermines the feature being demonstrated.
- **Public-use readiness:** demonstration checks pass, high-priority audit findings are resolved, real delivery/recovery/access controls are verified, and remaining failures have an accepted resolution. The current audit does not meet this gate.

Use this result record:

| Test ID | Environment / browser | Result | Evidence | Owner / next action |
| --- | --- | --- | --- | --- |
| Example: donor confirmation notification | Disposable fixture / browser version | Pass | Status change creates notification row | Repeat after workflow changes |

Do not replace individual results with “all tests passed.” Keep regression, investigative audit, manual UI and deployment evidence separate.

## Latest revision acceptance checks

1. Submit a fictional inquiry. Open its link on Contact Us in the same browser, then reply. Verify Staff > Contact Inbox shows the follow-up. Copy the numeric inquiry URL to a separate browser/session: it must fail. Test an expired email token separately.
2. Change a fixture account status with a reason. Verify the account's access changes, the reason persists and the notice says Preview. Retry the unchanged status and verify no duplicate notice.
3. Test a denied fixture screening: a one-month retry date appears and immediate resubmission is blocked. Have staff request reassessment. A clinical waiting period must still prevent booking.
4. Verify Pending blood is separate from Available stock and that measured mL totals agree with the inventory records.
5. Search/paginate Audit Log, combine actor/action/date filters and verify donors cannot access it.
6. Open cancellation, submit an invalid reason, then dismiss. The reason stays visible after rejection and the registration remains unchanged when dismissed.
7. Check My Account at desktop and mobile widths, OTP errors/resend cooldown, FAQ accordions, Safety link, campaign images and unsubscribe styling.

Implementation details and explicit mail limits: [October revision record](OCTOBER_REVISIONS.md). Do not mark real delivery as passed solely because a preview file exists.
