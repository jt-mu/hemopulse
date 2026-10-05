# HemoPulse revised workflows

## A — Authentication and security

`includes/security.php` is shared by both login endpoints, OTP registration, user management and first-admin setup. Passwords require 12–72 bytes, a letter and a number or symbol; common/repeated passwords are rejected. Existing hashes remain valid for login. `js/validation.js` applies the same creation policy. Admin user creation includes confirmation and independent Show/Hide controls.

Signup requires server-validated consent, names, email and password → email OTP → verification → hashed account creation and session regeneration. No endpoint returns the generated OTP. The session stores a hash of the code, expires it after ten minutes and permits five attempts. Resends have a 60-second email cooldown, five requests/hour per email and fifteen/hour per source. Verification also has email/source limits. Local mail previews are outside the web root and are never exposed through a browser endpoint.

Login uses five attempts per email per 15 minutes and fifty per source. Successful authentication clears the account counter. All protected operations reload the account status and role; deactivation takes effect on the next request. Logout destroys the session and cookie. Cookies use HttpOnly, SameSite=Lax and Secure when HTTPS is active. Inactivity timeout is 30 minutes. State-changing API requests need `X-CSRF-Token`; form endpoints need the hidden `csrf` field.

Public account references use `USR-` plus 24 random hexadecimal characters. Internal numeric keys and foreign keys are unchanged. Admin user search and staff screening search accept public references.

## B — Screening, campaigns and donation

`config/eligibility.php` defines preliminary whole-blood rules, reused by screening, booking, donation dates and homepage FAQs. The three-calendar-month donation interval and twelve-month tattoo interval follow [Philippine Red Cross guidance](https://redcross.org.ph/how-to-donate/). Month-end dates clamp to the final day of the target month. Staff make the final assessment; this is not a laboratory or diagnostic system.

Donor submits a screening → Eligible / Needs Review / Temporarily Deferred / Not Eligible. Staff/Admin open **Eligibility Reviews**, inspect relevant answers and submit a decision and donor-visible notes. **Reviewed** means another assessment is requested. A dated deferral blocks resubmission only until its review date. Expired deferrals allow a new record without rewriting historical answers. Reviews record reviewer/time, audit the decision and create a persistent donor notification. Staff cannot approve away an unexpired recorded donation interval or dated deferral.

Eligible screening for today → open campaign → generated 30-minute appointment within the campaign's start/end hours → consent and registration → Pending → Confirmed → Checked-In → Completed or Deferred. Closing time is exclusive. Disabled/full/expired campaigns remain visible but cannot be booked. Booking locks the donor and campaign to serialize capacity and duplicate checks.

Expired campaigns are evaluated dynamically. Moving a previously Closed expired campaign to a future date republishes it and clears an unchanged stale registration deadline. Active Pending/Confirmed bookings can move only when their existing times fit the new schedule; donors receive notifications. Events with attendance or donation history retain their historical schedule: create a new campaign instead. Explicitly Closed campaigns otherwise remain closed.

Staff record a completed donation once → registration locks as Completed → history/report updates → donor profile last/next donation dates update → Pending blood inventory and transaction are created atomically. The account remains Active. Another registration is blocked until the recorded next eligible date. Staff-supplied inventory expiry is required; it is not guessed. Clinical deferral requires a reason and future review date.

## C — Inventory and transactions

Pending → Available after staff confirm testing/processing → Reserved / Used / Discarded. Reserved can return to Available or become Used/Discarded. Expired can only become Discarded. Used/Discarded are terminal. Legacy Disposed values remain readable. Legacy Reserved stock is not silently relabelled because its testing state cannot be inferred safely.

Every new unit and inventory transaction has a random public reference. Unit provenance follows the existing donation transaction → donation → appointment → donor/campaign relationships. Authorized staff can see source and donor reference; public users cannot query stock. Expiry is evaluated at the start of the expiration date on inventory/report access and before status changes. The automatic expiry transaction is recorded once. Only nonexpired Available units count toward stock metrics.

**Transactions** is the inventory operations ledger: donation intake, stock release, status adjustments, expiration and disposal, with units, timestamp, responsible person, donor/campaign where known and notes. Other operational events, including campaign registration and account/review changes, use the existing audit log. Audit details exclude passwords and OTPs.

## D — Contact and newsletter

Contact form → validated and throttled inquiry → New. Staff/Admin can view content, mark Read, write/send a reply, or Close. Replies are persisted with responsible staff, time and transport result. Only mail-accepted replies mark the inquiry Replied. Preview mode saves a reply for local inspection and leaves it Read; the UI explicitly says no email was sent. Transport acceptance does not guarantee recipient delivery. External email cannot participate in a database transaction; investigate delivery before manually retrying a failed response.

Newsletter request → Pending Confirmation → expiring, hashed confirmation link → explicit POST confirmation → Subscribed. Email uniqueness prevents duplicate rows. Confirmation emails include a random unsubscribe link; explicit POST unsubscribe changes the existing row to Unsubscribed. Re-subscription issues a fresh confirmation. Admin alone can view the subscriber list. Neither tokens nor mail configuration are included in management API responses.

The deliberately supported phone format is `09` followed by nine digits (for example `09171234567`). Campaign, profile and user-management validation use that same format.

## E–G — Profile, interface and shared behavior

Donors can change their contact number and upload a validated JPEG/PNG/WebP profile photo. Identity and role are not writable through the profile handler. Photo limits remain 2 MB and 4096 pixels per side. Account management retains historical records when deactivating. Workspace sends a valid JSON object for DELETE and reloads the table after mutations.

Transient success notices have a close control and auto-dismiss. Workspace clears notices on navigation; persistent screening/donation updates appear in the donor notification section. Duplicate script imports were removed and form submissions are guarded while in flight. Asset versions use file modification times.

The existing blue/cream/yellow visual identity remains. Dashboard content aligns at the top; the existing location map is on Contact (there was no map on Locations). Its margins now align with surrounding content. CSS changes consolidate the conflicting map/dashboard rules; the large design stylesheet is retained to avoid an unrelated redesign.

## H — Verification and deployment

Run `php database/migrate.php` after backing up the database. It applies existing migrations plus `database/revision.sql`, then backfills random references using PHP's cryptographic random generator. The revision migration uses MariaDB's `IF NOT EXISTS` support and is tested on XAMPP MariaDB; do not assume the same DDL runs unchanged on Oracle MySQL. It deletes no records. Nullable clinical profile fields represent unknown legacy/demo data rather than invented values.

Run `php tests/integration.php` or `php test_runner.php`. The suite creates a randomly named disposable database, private sessions/mail previews and a separate local server, then cleans them up. It does not copy or modify live records or send real email. `--browser` keeps that fixture available for up to ten minutes for manual UI checks; the printed stop-file path ends it early. Syntax checks: PHP `-l` and Node `--check`.

Mail configuration is documented in `../config/mail.php` and [PDF_REVISIONS.md](PDF_REVISIONS.md). Use MAIL_MODE=smtp with the SMTP host, port, encryption, username, password, From and Reply-To values required by that configuration. PHPMailer is bundled in vendor. Preview mode sends no email; real inbox delivery still needs verification.
