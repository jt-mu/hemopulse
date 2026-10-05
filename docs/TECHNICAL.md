# HemoPulse technical documentation

## Architecture and roles

PHP renders public pages and protected dashboards. Shared `includes/bootstrap.php` manages sessions, CSRF tokens and current-user lookup. `api/index.php` routes REST requests to shared services in `includes/api_*.php`. JavaScript consumes that API using Fetch and same-origin session cookies. PDO connects to MySQL using native prepared statements. Bootstrap 5.3.8 is stored locally; the public pages use its grid alongside the existing HemoPulse styles, and the staff workspace uses its full components. Vendor source and license: https://getbootstrap.com/docs/5.3/getting-started/download/ and `assets/vendor/bootstrap/LICENSE`.

| Capability | Administrator | Staff | Donor |
| --- | --- | --- | --- |
| Manage users and roles | Yes | No | No |
| Create/edit/delete categories and campaigns | Yes | Read | Public campaigns |
| Register for a campaign | No | No | Own account |
| Review/confirm/check in registrations | Yes | Yes | View own status |
| Cancel registrations | Yes | Yes | Own, before event starts |
| Record donation outcome | Yes | Yes | No |
| Inventory, transaction ledger, contact inbox | Yes | Yes | No |
| Reports and CSV | All registrations | All registrations | Own registrations via API |

Supported roles are Administrator, Staff and Donor. Recipient fulfillment is disabled and Recipient cannot be assigned. The unused Recipient role is retained to preserve existing records, but it cannot be assigned through user management. Role restrictions are enforced by PHP on every protected endpoint, not just by hiding buttons. Account deactivation preserves historical records.

## Database design

The installed schema exceeds eight related tables. Core relationships are:

| Table | Key / relationship |
| --- | --- |
| roles | role_id PK; unique role name |
| users | user_id PK; role_id → roles; unique email |
| campaign_categories | category_id PK; unique name |
| campaigns | campaign_id PK; created_by → users; category_id → campaign_categories |
| appointments | appointment_id PK; donor_id → users; campaign_id → campaigns |
| eligibility_checks | eligibility_id PK; user_id → users |
| campaign_registrations | registration_id PK; appointment_id → appointments; eligibility_id → eligibility_checks |
| donor_profiles | donor_profile_id PK; user_id → users |
| donation_records | donation_id PK; appointment_id → appointments; verified_by_staff_id → users |
| blood_inventory | inventory_id PK; collected units and their stock state |
| inventory_transactions | transaction_id PK; inventory_id → blood_inventory; staff → users; donation_id → donation_records; request_id → blood_requests |
| blood_requests | request_id PK; requester → users |
| request_fulfillments | fulfillment_id PK; request → blood_requests; inventory → blood_inventory |
| audit_logs | log_id PK; user_id → users |

Independent supporting tables store contact messages, newsletter subscriptions and hashed login-attempt identities. Notifications and inventory thresholds are used for donor decisions and staff stock indicators.

Roles, users, campaign categories, campaigns, registrations and clinical outcomes are separated to avoid repeating descriptive data in every transaction. Junction/reference tables use foreign keys, unique constraints and indexes. Registration contact/consent data is an intentional historical snapshot. Capacity and stock balances are stored operational totals maintained inside transactions. Screening answers are a JSON questionnaire snapshot, not duplicated relational master data.

`database/migrate.php` is additive and repeatable. It creates missing workflow tables, extends existing columns and establishes foreign keys. `deployment/schema.sql` is the clean base schema for new installs; migrate after importing it. The obsolete sample-data dump has been removed. Tests and fresh installs both use this clean schema. Optional demonstration campaigns are added with `php scripts/seed_campaigns.php`.

## Registration lifecycle

Pending → Confirmed → Checked-In → Completed or Deferred. Pending/Confirmed can become Cancelled; Confirmed can become No-Show. Attendance cannot be recorded before the donation event date. Invalid transitions return 409. Registration locks the donor and campaign while checking eligibility, duplicate bookings and remaining capacity. Cancellation restores capacity only on its first successful transition. Donation intake locks the registration, rejects repeated clinical outcomes, and writes the outcome, stock addition, ledger and audit entry in one transaction. New stock is Pending until laboratory review; this portal does not automate lab clearance or clinical decisions.

Public campaign status is OPEN, FULL or CLOSED and does not expose capacity counts. Public signup cannot select a privileged role. Deleting a referenced campaign/category is rejected; close an event instead to keep its history.

## REST API

Base URL on XAMPP: `http://localhost/hemopulse/api/index.php`. Apache rewriting also supports `/api/...`. The built-in development server uses `router.php`; a query fallback is `index.php?route=campaigns`.

| Method and path | Access / purpose |
| --- | --- |
| GET /session | Current user and CSRF token |
| GET /campaigns | Public paginated campaigns; `scope=management` requires staff |
| POST /campaigns, PUT /campaigns/{id}, DELETE /campaigns/{id} | Admin campaign CRUD |
| GET /categories | Public category list |
| POST /categories, PUT /categories/{id}, DELETE /categories/{id} | Admin category CRUD |
| GET /users, POST /users, PUT /users/{id}, DELETE /users/{id} | Admin user management; DELETE deactivates |
| GET /appointments | Own donor records or all staff records |
| POST /appointments | Donor registration, using the same validation as the PHP form |
| PUT /appointments/{id} | Staff status transition |
| DELETE /appointments/{id} | Authorized cancellation |
| POST /donations | Staff clinical outcome entry |
| GET /inventory, GET /transactions | Staff stock and ledger |
| GET /messages, PUT /messages/{id} | Staff contact inbox and response status |
| GET /reports | Aggregates, monthly totals, popular campaigns, recent activity |
| GET /reports?format=csv | Scoped downloadable report |

Collections return `data` plus `meta` for paginated resources. Supported collection query parameters include `q`, `status`, `from`, `to`, `sort`, `page`, `limit` (maximum 50); resource-specific fields are validated. Campaigns additionally accept venue/category filters. Sort values are allowlisted. Reports filter by donation event date; recent activities remain the latest system actions. Methods not supported by a route return 405.

Mutations require JSON, an authenticated session where applicable, and the `X-CSRF-Token` header returned by `/session`. The frontend uses this pattern:

```javascript
const session = await fetch('api/index.php/session').then(r => r.json());
const response = await fetch('api/index.php/appointments/123', {
  method: 'PUT',
  headers: {'Content-Type': 'application/json', 'X-CSRF-Token': session.data.csrf},
  body: JSON.stringify({appointment_status: 'Confirmed'})
});
const result = await response.json();
if (!response.ok) throw new Error(result.message);
```

See `js/workspace.js`, `js/donor-dashboard.js`, and `js/search.js` for working API consumers and exact payload fields. Native form handlers are retained for login, screening, registration, contact and newsletter submissions. API errors use JSON and appropriate 400/401/403/404/405/409/413/415/422/503 statuses. SQL details are logged privately rather than returned to clients.

## Security and usability

- Passwords use PHP password_hash/password_verify. Login regenerates the session ID; logout destroys the session; 30 minutes of inactivity expires authentication. Cookies use HttpOnly, SameSite and Secure under HTTPS.
- Failed login attempts are rate-limited per hashed email/address identity. Privileged writes require role checks and CSRF validation. The final active administrator cannot be removed by the user-management API.
- PDO binds input values; dynamic SQL identifiers and sort expressions are allowlisted. PHP HTML output is escaped, and new JavaScript views use textContent rather than inserting user HTML. CSV formula prefixes are neutralized.
- Required fields, dates, lengths, numeric ranges and email formats are checked server-side as well as through client-side form controls. The UI displays API validation failures and supports retrying failed loads.
- Bootstrap grids, responsive navigation, scrollable tables, labeled controls, visible focus, a skip link, dialog forms, and chart data tables support smaller screens and keyboard users. This is not a formal WCAG certification.
- Apache deny rules protect server configuration, SQL, tests and documentation; the development router provides corresponding restrictions.

## Rubric evidence and testing

| Requirement | Demonstration |
| --- | --- |
| Responsive interface / Bootstrap | Public pages and staff workspace |
| PHP routing, sessions, authentication | bootstrap.php, backend/auth_handler.php, api/index.php |
| 3 roles / authorization | Role matrix above; automated 403 checks |
| MySQL, related tables, CRUD | Schema above; campaigns/categories/users management |
| JS, validation, DOM, Fetch, API consumption | Three API-consuming JS modules |
| Search/filter/sort/pagination | Campaign browser, registrations and workspace lists |
| Dashboard/chart/reports | Staff Overview and Reports; CSV export |
| Error handling and security | Validation, permission, conflict and CSRF tests |
| Deployment and documentation | Verified local XAMPP installation; DEPLOYMENT.md and release ZIP |

Run `php tests/integration.php`. The suite exercises the current authentication, authorization, validation, screening, campaign, donation, inventory, contact and newsletter workflows. It uses a disposable database, server and private mail/session directories. Run PHP lint and Node syntax checks alongside it.

For manual QA, follow the role demonstration in DEPLOYMENT.md, inspect desktop and narrow-screen layouts, and check network responses through browser developer tools. Verify that an API status change appears after refreshing the donor page and that the database row matches. Browser checks supplement backend tests; they do not replace them.

Bonus scope: local email previews and delivery configuration are included; existing map display is retained; reports can be saved as PDF through browser printing. A QR image/scanner, automatic lab clearance, push notifications, native PDF generation, PWA, AI features, password recovery and production email delivery are not implemented. An external API is optional under the rubric: the application's own REST API is created and consumed.


### Revision implementation

`users.middle_name` is nullable and limited to 50 characters. `users.profile_image` stores a generated filename. `backend/profile_handler.php` validates session, CSRF, MIME, image dimensions and size, updating only the session owner's record. `profile_photo.php` serves only that owner's image with a fixed MIME type and nosniff. Apache and the development router deny direct access to storage.

Visual overrides: `css/design.css`. Workspace layout: `workspace.php`. Public footer: `includes/footer.php`. Conditional fields: `js/eligibility.js` (server validation retained). Profile upload validation remains in includes/profile_upload.php. The HTTP suite covers profile escaping and account access; photo rendering still needs manual upload QA.
