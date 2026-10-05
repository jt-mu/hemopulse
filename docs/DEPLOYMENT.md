# Deployment and demonstration

## Current local installation

The application is deployed in `C:\xampp\htdocs\hemopulse` and uses the configured `Hemopulse_db` database. The additive migration has been applied without replacing existing records. Start Apache and MySQL in XAMPP and open http://localhost/hemopulse/index.php.

There is no default administrator password. Create the first administrator from PowerShell in the project folder:

```powershell
.\scripts\create-admin.ps1
```

Enter your email and a password of 12–72 bytes in the credential prompt. The password is hashed. The helper clears its temporary credential environment variables and refuses to create another administrator when an active administrator already exists. Then log in normally; Administrator and Staff accounts open `workspace.php`. Use **Users → New user** to create Staff accounts. Public signup creates Donor accounts only. Use **Categories** and **Campaigns** to enter actual donation events. No demonstration users or campaigns are added to the live database by tests.

## Fresh installation

Requirements: PHP 8.2 with PDO MySQL, mbstring, sessions and JSON; XAMPP MariaDB 10.4+ (the revision migration uses MariaDB-specific repeatable DDL); Apache 2.4 with rewrite support for clean API URLs. The PHP integration tests additionally require cURL and permission to create/drop disposable databases.

1. Extract the release ZIP into your web directory, retaining `.htaccess` files.
2. Create an empty `Hemopulse_db` database with utf8mb4 encoding.
3. Import `deployment/schema.sql` through phpMyAdmin into that empty database. This schema contains role definitions but no donor, staff, campaign, or clinical data. Never import a fresh schema over an existing installation.
4. Set database environment variables as needed, then run `php database/migrate.php` from the project directory. This creates the additional workflow tables and foreign keys. Run this migration when upgrading existing installations too, after taking a database backup.
5. Run `scripts/create-admin.ps1` on Windows. On other systems, securely set `HEMOPULSE_ADMIN_EMAIL` and `HEMOPULSE_ADMIN_PASSWORD` for a one-time `php scripts/create_admin.php` process, then unset them.
6. Log in, create Staff accounts, categories and campaigns, and complete the demonstration below. For fictional initial campaigns, optionally run `php scripts/seed_campaigns.php`. See README.md for role-by-role instructions and the phpMyAdmin alternative for first-admin setup.

## Public hosting

Public hosting has not been provisioned. Upload the release to an Apache/PHP/MySQL host, import the clean schema and run the migration using the host's terminal. Set these values in the hosting environment, not in committed code:

| Variable | Purpose |
| --- | --- |
| HEMOPULSE_DB_HOST | Database hostname |
| HEMOPULSE_DB_NAME | Database name |
| HEMOPULSE_DB_USER | Dedicated application database user |
| HEMOPULSE_DB_PASS | Database password |
| HEMOPULSE_APP_URL | Exact HTTPS base URL, including any subfolder |
| HEMOPULSE_MAIL_TRANSPORT | `preview` until real delivery is configured; then `mail` |
| HEMOPULSE_MAIL_FROM | Verified sender address |
| HEMOPULSE_MAIL_PREVIEW_DIR | Private writable directory outside the web root |

Use HTTPS, `display_errors=Off`, private PHP error logs, and a private writable session directory. Give the runtime database user only the privileges required for normal queries; use a separate migration credential for schema changes. Ensure Apache honors `.htaccess` (`AllowOverride` with the required permissions and `mod_rewrite`). For another web server, translate the deny rules before publishing: block `config`, `includes`, `database`, `scripts`, `tests`, `docs`, `deployment`, hidden files, SQL dumps, Markdown, environment files, logs and email previews. Do not expose phpMyAdmin publicly.

Both `/api/campaigns` and `/api/index.php/campaigns` should return JSON. If PATH_INFO or rewriting is unavailable, `/api/index.php?route=campaigns` is the supported fallback. Verify the site's JS API paths on the chosen server. A reverse proxy must provide PHP with a trustworthy HTTPS configuration so secure session cookies are enabled.

Mail configuration is documented in `../config/mail.php` and [PDF_REVISIONS.md](PDF_REVISIONS.md). Use MAIL_MODE=smtp with the SMTP host, port, encryption, username, password, From and Reply-To values required by that configuration. PHPMailer is bundled in vendor. Preview mode sends no email; real inbox delivery still needs verification.

Back up the database before upgrades; retain the previous release for code rollback. Restore the corresponding database backup only when schema rollback is necessary. Verify login, API permissions, forms, reports, and deny rules after deployment.

## Demonstration script

1. Administrator logs in, creates a category and an upcoming Published campaign; edits its description and searches for it.
2. Donor registers, logs in, completes preliminary screening and submits the campaign registration. The dashboard displays Pending. Clinical eligibility remains a staff decision.
3. Staff logs in, searches registrations and confirms the request. Donor refreshes their dashboard to see Confirmed.
4. A donor cancellation releases capacity once. An event-day check-in allows staff to record a Completed or Deferred outcome. Staff must supply the actual clinical information and dates; newly collected units remain Pending until laboratory review.
5. Administrator opens Reports, applies event-date filters, views the monthly chart and downloads CSV. The Print / Save PDF button uses the browser's print dialog.
6. Demonstrate a donor receiving 403 when requesting staff resources, an invalid form receiving validation feedback, and a missing CSRF token being rejected.

Use the disposable automated test preview for fictional clinical scenarios, never the live database:

```powershell
& C:\xampp\php\php.exe tests\integration.php --browser
```

The terminal prints the disposable fixture URL and test-only account credentials. The fixture expires after ten minutes; create the printed stop file to end it earlier. Cleanup removes the test database, sessions and previews. These credentials do not exist in a clean deployment.

