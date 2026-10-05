# October 4 revision implementation

Implemented from the latest 16-page HemoPulse revision PDF. The user approved clearly fictional, consistent demo contacts: **0900-000-0000** and **team@hemopulse.example**. These replace the document's request for a real contact number.

## Changes

- Shared account layout puts contact beside middle name, below last name. Staff do not receive donor-only account correction guidance. Mobile inputs retain normal heights.
- Overview separates Pending, Available, Reserved, Expired, Used and Discarded inventory. Available volume uses the recorded mL per unit. Inventory lists that measured volume.
- Full Audit Log has pagination, search, actor, action and date filters. Sign-in/out, screening, profile updates, inquiry submissions and follow-ups are recorded alongside administrative actions. Overview links to it.
- Staff and donor cancellation use the same styled dialog. Rejected submissions retain the reason. Future attendance actions are hidden; backend date checks remain authoritative.
- Contact conversations show original messages, team replies and visitor follow-ups. The browser that submits an inquiry receives a session-protected conversation link on Contact Us, so demo continuation works without email. Other browser sessions cannot access it by numeric ID. Session access expires when the session ends or after 30 days. Team emails also contain a secret 30-day link; the newest team reply replaces older email links. Neither link grants workspace access.
- Account status changes require a reason and save a notice attempt atomically. Status updates take effect even when email transport fails. Retrying unchanged status does not send another notice. Delivery status is visible in Users.
- Not Eligible screening has a one-calendar-month retry pause. Migration derives existing denial dates from their review/submission date. Staff can request reassessment; this does not override a clinical donation interval or dated deferral. Waiting notices and progress reflect the current screening cycle.
- OTP resend is styled with a cooldown. Validation, authentication and resend errors appear inside the relevant form; fields remain available for retry.
- Notifications include an icon; workspace Sign Out has a hover/focus transition. Home and Help Desk use matching FAQ accordions. Safety has a working destination.
- Contact form heading is larger, the requested introductory form text is removed, and session/IP/email limits prevent rapid submissions. Newsletter uses newsletter.png without a color filter. Campaign cards use the supplied community, student, corporate and weekend images. Locations title and unsubscribe button follow the requested design.
- Filters and sorting are specific to each tab. Editors show field constraints, account status reasons and volume hints.

## Verification and demo readiness

Run `C:\xampp\php\php.exe test_runner.php`. **190 automated checks pass**, covering role/CSRF protection, state transitions, date boundaries, retry/idempotency, notice tracking, contact access, volume and audit pagination. PHP and JavaScript syntax checks also pass. Browser checks covered desktop/mobile account fields, cancellation rejection/dismissal, available-stock summary and contact design.

Run `test_runner.php --browser` for a disposable demonstration fixture. It prints a temporary URL and credentials, forces mail preview mode, and removes its database after ten minutes. Use the [readiness guide](READINESS_GUIDE.md) for manual acceptance checks.

Mail defaults to **Preview**: private .eml files outside the website. No real mailbox delivery was configured or verified. **Accepted** means a configured transport accepted the message, not that an inbox received it. The document's requested real OTP/email delivery remains dependent on a real sender, SMTP credentials and an inbox test. Fictional addresses cannot receive mail. Configure real mail only when the demo is being turned into an operational service.

Newsletter submissions remain Pending until confirmation. Admin > Newsletter lists consent/status/delivery. This application does not automatically send a newsletter campaign to all subscribers.

The migration is additive and preserves existing records: `php database/migrate.php`. It adds contact follow-ups, status notices and retry dates. The ERD/API guide now reflects 24 tables and 30 physical foreign keys. The deployment ZIP includes these changes, PHPMailer and the test suite.
