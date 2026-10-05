# PDF revision comparison and implementation

Source: HemoPulse - Revisions.pdf, October 4, 2026 (17 revisions). The user authorized comparison and implementation. Existing code and relationships were retained.

| PDF item | Result / main files |
| --- | --- |
| 1, 9 | Progress appears only on campaign registration, not My Account, screening detail, or My Donations. Notifications has a dedicated sidebar view. `dashboard.php` |
| 2, 6 | Shared `includes/progress_state.php` separates the current cycle from completed history. Browsing a URL does not complete selection. A CSRF-protected POST saves selection against a current screening; active bookings use their recorded screening relationship. Waiting preserves completed donation/history, and a new cycle resets its steps without deleting history. |
| 3 | Consistent top-right dismiss controls, reserved text space, success timeout, shared notice styles. `js/notices.js`, `js/workspace.js`, `css/design.css` |
| 4 | Random REG references stored and backfilled on appointments; donor history, staff lists and donation editor display them. Staff search supports the reference. Internal numeric keys remain in authorized API operations. |
| 5 | Staff account uses shared photo upload/contact forms, read-only identity and role, with redirects back to the workspace. Sidebar, typography and cards retain the shared design. |
| 7, 8 | Visitor FAQs categorized; dedicated system FAQs cover eligibility, review, registration/status/cancellation, locations, history, notifications, profile, newsletter and contact. `index.php`, `helpdesk.php`, `includes/system_faq.php` |
| 10 | Confirmation and unsubscribe reuse the standard footer and styles. |
| 11 | Responsive labeled filter grid; existing combined server-side filtering retained. |
| 12 | Known seeded prototype titles/venues renamed, with fictional-event descriptions retained. Only exact known DEMO seed records are migrated; dates, status, registrations and clinical records remain intact. Future seed runs use the revised titles. |
| 13 | Newsletter uses the reference-matching `images/model-art.png` with an overlay and responsive contain sizing (the separately named newsletter asset depicts a different scene). |
| 14 | Contact cards/footer use `0900-000-0000 (sample only)`; no callable personal number was introduced. |
| 15 | Metric grid: four columns large, two medium, one small; stock table follows separately. |
| 16 | Transaction table reduced to six columns; expandable details preserve full notes and provenance. Search added; existing server pagination verified for distinct pages, counts, last-page clamping and retained filters. |
| 17 | Shared mailer supports SMTP via bundled PHPMailer 7.1.1, TLS, authentication and Reply-To; preview remains available. No credentials supplied and no real inbox delivery claimed. |

## Migration

Run `C:\xampp\php\php.exe database\migrate.php` for other installations. Applied successfully on this workspace database. The repeatable `database/revision.sql` adds `appointments.public_reference`, its unique index and `donor_campaign_selection`; PHP migration backfills cryptographically random references. Known sample campaign text updates preserve keys and history.

## SMTP configuration

Configure these in the environment of the Apache/XAMPP process, outside the repository, then restart Apache. PHP does not automatically read a `.env` file in this project.

```text
MAIL_MODE=smtp
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURITY=tls
SMTP_USERNAME=<sender Gmail address>
SMTP_PASSWORD=<Google App Password>
HEMOPULSE_MAIL_FROM=<authorized sender address>
MAIL_REPLY_TO=<monitored team mailbox>
HEMOPULSE_APP_URL=<actual reachable HemoPulse URL>
```

Port 465 with `SMTP_SECURITY=ssl` is also supported. Keep certificate verification enabled. Gmail must permit the account's chosen authentication method; use an App Password where supported, not a password committed to source. OTP, newsletter confirmation and contact replies all use this transport. A successful SMTP response means server acceptance, not guaranteed inbox placement.

For local development set `MAIL_MODE=preview`. `HEMOPULSE_MAIL_TRANSPORT` remains a legacy fallback when MAIL_MODE is unset. Preview files remain outside the web root. SMTP requires a valid sender and monitored Reply-To; errors are generic for users, with no credentials, bodies or server transcripts logged. PHPMailer source/license/version are in `vendor/phpmailer`, denied direct Apache access.

## Verification and limits

117 integration checks passed; all 67 PHP files and 10 JavaScript files passed syntax checks. The integration suite uses an isolated disposable database and explicitly forces preview mail, even when the machine uses SMTP. It covers progress cycles, public-reference search, protected profile updates, newsletter footers, transaction pagination/search, combined filters, SMTP configuration failure, and the original auth/donation/inventory tests.

Browser checks covered responsive metric cards, staff account controls, transaction search and Next: page 2 of 2 retained the search and disabled Next, with no console errors. Contact cards show the sample number.

Real Gmail delivery requires configured credentials and a recipient acceptance test. No live email was sent. Exhaustive device and assistive-technology testing is not claimed.

## Follow-up gap fixes

The eligibility page now displays journey progress during the post-donation waiting period as well as registration. My Account and My Donations do not repeat the tracker. A saved selection for the latest screening restores in a fresh session; explicitly browsing a different campaign still takes precedence.

Deleting an unbooked campaign atomically clears its unsubmitted selections. Campaigns with registration history remain protected. The dedicated notification page now provides 20-item pagination, unread counts and labels, and CSRF-protected mark-as-read forms scoped to the signed-in user. Pagination is retained after marking a record read.

Eligibility-date and saved-selection queries are inside the dashboard's guarded loading path. Notification failures have a separate friendly fallback. These failures cannot silently enable registration.

Follow-up verification: 132 integration checks passed, including independent-session selection restoration, waiting-period page rendering, protected campaign history, notification ownership/CSRF/pagination and deliberately unavailable database tables. All 69 PHP files passed lint. No additional schema migration was required. Real SMTP delivery and self-service password recovery remain outside these five fixes.
