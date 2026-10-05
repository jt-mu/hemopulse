# HemoPulse project guide

PHP/MySQL blood donation management project with three main roles: Customer/Donor, Employee/Staff and Administrator. Pages use `.php`; CSS, JavaScript and images remain separate because they provide styling, browser interaction and media.

## Run the website on this computer

1. Open XAMPP Control Panel.
2. Start **Apache** and **MySQL**.
3. Open http://localhost/hemopulse/index.php in your browser. Do not double-click PHP files.

The database on this computer is already initialized. Do not import the schema again over an existing database. If you see “Account service is unavailable,” check that MySQL is running, the credentials in `config/database.php` are correct, and `hemopulse_db` contains its tables. Server errors are recorded in `C:\xampp\apache\logs\error.log`.

## Customer / Donor

1. Open the homepage, choose Account → Register, accept the terms and request an email verification code. Use 12–72 bytes with a letter and a number or symbol; avoid common or repeated passwords. In local preview mode, inspect the server-side email preview to obtain the code.
2. Log in using the same email and password. Public registration always creates a Donor.
3. Browse Locations and select a campaign using Register Now.
4. Complete the preliminary eligibility questionnaire. An eligible result for the current day is required to submit registration; final medical eligibility is assessed by donation staff.
5. Complete your registration details and agreement, then submit.
6. Open your dashboard's donation section to view Pending, Confirmed or other statuses. You can cancel your own eligible upcoming registration before the event starts.
7. Use Account → Logout when finished.

## Administrator: first account setup

There is no built-in admin email or default password. Choose one of these methods.

### Using phpMyAdmin (no PowerShell)

1. Register an account through the website, preferably with an email reserved for administration, then log out.
2. Open http://localhost/phpmyadmin and select `hemopulse_db`.
3. Open `roles` and find the `role_id` for `Admin`.
4. Open `users`, find your account by email, and click Edit.
5. Set its `role_id` to that Admin ID and ensure `account_status` is `Active`. Click Go to save. Do not edit `password_hash`.
6. Log in to the website with the same email/password. You will enter Administrator Workspace.

### Using the setup script

Open PowerShell in the project folder and run:

```powershell
cd C:\xampp\htdocs\hemopulse
.\scripts\create-admin.ps1
```

Supply an unused email and a password of 12–72 bytes in the prompt. This script only creates the first active administrator. If your computer blocks PowerShell scripts, use the phpMyAdmin method above.

### Administrator tasks

- Users: create Employee accounts with role **Staff**, edit users, and deactivate accounts while keeping their history.
- Categories: create and manage campaign categories.
- Campaigns: create events, change descriptions/capacity, publish or close events. Campaigns with registration history cannot be deleted.
- Registrations: confirm, cancel and process attendance.
- Inventory / Transactions: process tested units and review the inventory ledger and provenance.
- Contact Inbox: view inquiries, mark Read, compose email replies, and Close. Local preview replies are labelled accurately and do not mark an inquiry Replied.
- Reports: filter by event date, view totals and charts, download CSV, or use Print / Save PDF.

## Employee / Staff

1. Ask the administrator to create your account through **Users → New user**, choosing role **Staff**, Active status, your email and an initial password.
2. Log in through the same homepage login form. The site opens Staff Workspace automatically; there is no separate staff login page.
3. Open Registrations, search/filter donors and confirm Pending registrations.
4. On the donation event date, check in a confirmed donor or record a no-show. Future events cannot be checked in.
5. For a checked-in registration, choose Record outcome and enter the actual staff-assessed clinical outcome and required dates/details. Completed donations create Pending inventory and a transaction entry pending laboratory review.
6. Review reports, inventory, transactions and contact messages. Staff cannot manage user accounts, categories or edit campaigns.
7. Sign out when finished.

Use separate browser profiles or sign out between roles when demonstrating the project.

## Initial campaigns

Three fictional campaigns were added for classroom demonstration:

| Campaign | Category | Initial event date | Venue |
| --- | --- | --- | --- |
| Community Donation Drive (DEMO) | Community | September 27, 2026 | Demo Community Hall |
| Campus Blood Donation Day (DEMO) | Campus | October 4, 2026 | Demo Campus Activity Center |
| Workplace Donation Campaign (DEMO) | Workplace | October 11, 2026 | Demo Workplace Function Hall |

All run 08:00–16:00, with registration closing at 15:00 on the event date. They are Published and can be used to test booking. They are not real public blood drives. Replace the sample details with confirmed event information before public use. Existing registration history can prevent changing event dates or venues; create a new real event when appropriate.

For another installation, run the optional seed after migration:

```powershell
& C:\xampp\php\php.exe scripts\seed_campaigns.php
```

It creates campaigns 7, 14 and 21 days after the day it runs. Rerunning skips matching demo titles without changing existing dates, capacity or registrations. It does not create users or clinical data.

## What is in the folder?

| Path | Purpose / project requirement |
| --- | --- |
| index.php | Homepage and shared login/register modal |
| locations.php | Campaign discovery, search, filters and pagination |
| dashboard.php | Customer/Donor dashboard |
| workspace.php | Employee/Administrator dashboard and management screens |
| contact.php / helpdesk.php | Contact form and help information |
| newsletter_confirm.php | Newsletter double opt-in confirmation |
| api/ | REST routes consumed with JavaScript Fetch |
| backend/ | Server-side login, registration, eligibility, contact and transaction handlers |
| includes/ | Reusable page sections, authentication checks, validation and database services |
| config/ | Database connection and email settings |
| database/ | Repeatable migrations and additional table definitions |
| deployment/schema.sql | Clean base database for a fresh installation; contains roles but no personal data |
| css/ | Website and responsive dashboard styles |
| js/ | Used browser scripts: menus, forms, Fetch, filtering and dynamic content |
| images/ | Website images and branding |
| assets/vendor/bootstrap/ | Local Bootstrap framework and its license |
| scripts/ | First-admin creation, optional sample campaigns and release packaging |
| tests/ | Automated verification of authentication, roles, CRUD, security and workflows |
| docs/ | Detailed technical/rubric documentation and deployment instructions |
| router.php | Routing and private-file protection for the development/test PHP server |
| test_runner.php | CLI shortcut for the integration suite |
| .htaccess files | Apache API routing and private-file access protection; keep these |
| README.md | This operating guide |

Keep tests and documentation for your project demonstration. Keep configuration, SQL and hidden access rules even though users do not see them in the browser.

Removed during cleanup: obsolete `Hemopulse_db.sql` sample-data dump, unused `js/api.js`, empty `js/locations.js`, unused `js/validation.js`, and the generated old release ZIP. The revision adds a small shared js/validation.js for account password rules; server validation remains authoritative. The authentication image path was corrected to the existing image.

## Test the connections and workflows

With MySQL running, use PowerShell in the project directory:

```powershell
& C:\xampp\php\php.exe tests\integration.php
```

The suite runs HTTP/database checks against a disposable database and separate local PHP server. It cleans up its test database, sessions and email previews afterward and never sends real email. See docs/WORKFLOWS.md and docs/PHASE3_CHECKLIST.md for implementation evidence.

Newsletter email uses local previews: submit the Contact page newsletter form, inspect the `.eml` under PHP's temporary `hemopulse-mail-previews` folder, follow its link, and confirm. It does not send actual email. Real delivery requires server mail configuration described in docs/DEPLOYMENT.md.

## Install elsewhere / submit the project

See [deployment instructions](docs/DEPLOYMENT.md) and [technical documentation and rubric mapping](docs/TECHNICAL.md). A fresh installation imports `deployment/schema.sql` once, then runs `php database/migrate.php`. Optional sample campaigns are a separate explicit step.

To share a clean group-demo copy, run `scripts/package.ps1`; it generates `deployment/hemopulse-group-demo.zip`. The archive excludes uploaded profile photos, test databases, accounts, registrations, messages and all other live records. Group members import the clean schema and run the campaign seed script using [GROUP_SETUP.md](deployment/GROUP_SETUP.md). For a school source-code submission, include the source folder with `tests/` and `docs/`; the runtime ZIP intentionally excludes tests. Public hosting and real email delivery are not configured.

## Updated design and profile features

Admin and Staff now share the donor dashboard's blue sidebar, cream cards and responsive layout. Public pages share a footer, Contact has a full-width map, and Help Desk FAQs expand without an inner scrollbar. Signup accepts an optional middle name. Eligibility dates appear only for the relevant Yes answers.

Donors can upload profile photos from My Account: JPEG, PNG or WebP, up to 2 MB and 4096 pixels per side. Images are stored in `storage/profiles` and served only to their owner through `profile_photo.php`. Back up that folder with the database. Release packages exclude uploaded photos. PHP needs fileinfo and writable upload storage. `HEMOPULSE_PROFILE_DIR` can select a private directory outside the web root.

After updating an older installation, run `php database/migrate.php`. This repeatable migration adds the profile and middle-name columns without replacing existing records.

For sample data, run `php scripts/seed_campaigns.php`, followed by `php scripts/seed_registrations.php`. The latter adds one Pending booking per existing active donor to the future Community Donation Drive (DEMO), subject to capacity. Reruns preserve existing bookings, including cancelled ones. These are fictional project examples, not real appointments. No consent, screening or clinical records are generated. Cancel examples through the normal registration controls when finished.

## Full system revision (October 2026)

The revision is implemented in the existing project. Read [final workflows](docs/WORKFLOWS.md) and the [87-item Phase 3 checklist](docs/PHASE3_CHECKLIST.md). Run the additive migration with `php database/migrate.php`; exact MariaDB DDL is in `database/revision.sql`. Internal primary keys remain unchanged and no records are deleted.

Staff/Admin have Eligibility Reviews and inventory processing. Admin has newsletter management. Contact replies, consent-protected OTP signup, secure public references, temporary deferrals, calculated next donation dates, persistent donor notifications and dismissible success notices are integrated into existing pages.

Mail defaults to **preview**, for OTPs, contact replies and newsletter messages. This does not deliver email to a mailbox. Real delivery needs the configured PHP mail transport. Existing attendance/donation history prevents rescheduling an old event; create a new campaign to preserve that history. The existing embedded map is on Contact, not Locations.

Earlier PDF revision implementation and SMTP setup: [docs/PDF_REVISIONS.md](docs/PDF_REVISIONS.md).

Current logic findings and duplication review: [docs/LOGIC_AUDIT.md](docs/LOGIC_AUDIT.md).
Manual acceptance steps and release gates: [docs/READINESS_GUIDE.md](docs/READINESS_GUIDE.md).
Run `php tests/integration.php --audit` to reproduce the investigative probes; read `GAP` observations separately from passing regression checks.

October 4 logic fixes: 146 integration checks, including the original audit probes; shared notification/progress helpers; durable contact reply attempts; PHPMailer and tests bundled in the release. Run `php database/migrate.php` on existing installations before using the updated intake and reply features. See [readiness guide](docs/READINESS_GUIDE.md).

Latest 16-page revision implementation: [October revisions](docs/OCTOBER_REVISIONS.md). Includes session-protected contact conversations, account status notices, one-month screening retry dates, full audit browsing, inventory volume and UI corrections. Current suite: 190 passing checks.
