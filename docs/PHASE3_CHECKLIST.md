# Phase 3 implementation checklist

Updated October 4, 2026. See WORKFLOWS.md for behavior, migration details and deployment limits. “Implemented” describes code; evidence is scoped to the checks listed, not a claim of exhaustive clinical or browser certification.

Final verification: 100 integration checks passed; 61 PHP files passed lint and 10 JavaScript files passed syntax checks. Browser checks covered Donate → Login, administrator login, user password controls, screening review and inventory; no console errors appeared in those checks.

## Phase A

Modules: includes/security.php; backend/{login,auth,otp}_handler.php; js/validation.js.

Evidence: HTTP login, CSRF, OTP expiry/retry/resend, account validation.

| Item | Requirement | Status / qualification |
| --- | --- | --- |
| 1 | Remove Demo OTP Exposure | Implemented |
| 2 | Consolidate Authentication | Implemented |
| 3 | Add Login Rate Limiting | Implemented |
| 4 | Add OTP Rate Limiting | Implemented |
| 5 | Improve Session Security | Implemented |
| 6 | Update Password Requirements | Implemented |
| 7 | Add Show/Hide Password | Implemented |
| 8 | Secure Account Reference IDs | Implemented |

## Phase B

Modules: includes/eligibility.php; api_workflows.php; api_campaigns.php; api_records.php; booking.php.

Evidence: HTTP screening/review, reschedule, slot boundary, donation lifecycle.

| Item | Requirement | Status / qualification |
| --- | --- | --- |
| 9 | Staff/Admin Eligibility Monitoring | Implemented |
| 10 | Fix "Needs Review" Dead End | Implemented |
| 11 | Do Not Permanently Lock Temporary Ineligibility | Implemented |
| 12 | Centralize Eligibility Rules | Implemented |
| 13 | Keep Eligibility Information Consistent | Implemented |
| 14 | Fix Campaign Reopening | Implemented; events with attendance/donation history retain their schedule |
| 15 | Recalculate Campaign Status Correctly | Implemented; events with attendance/donation history retain their schedule |
| 16 | Improve Campaign Availability | Implemented |
| 17 | Dynamic Appointment Times | Implemented |
| 18 | Fix Closing-Time Bug | Implemented |
| 19 | Fix Post-Donation Confirmation | Implemented |
| 20 | Automatically Complete Donation Registration | Implemented |
| 21 | Do Not Lock the Entire Donor Account | Implemented |
| 22 | Calculate Next Eligible Donation Date | Implemented |
| 23 | Update Donation History Immediately | Implemented |

## Phase C

Modules: includes/api_workflows.php; api/index.php; js/workspace.js.

Evidence: HTTP inventory processing/expiry, transactions and metrics.

| Item | Requirement | Status / qualification |
| --- | --- | --- |
| 24 | Complete the Blood Inventory Workflow | Implemented |
| 25 | Inventory Information | Implemented |
| 26 | Update Inventory From Donation Workflow | Implemented |
| 27 | Handle Expiration | Implemented |
| 28 | Inventory Statuses | Implemented |
| 29 | Low Inventory Indicators | Implemented |
| 30 | Define the Transactions Page | Implemented |
| 31 | Transaction Information | Implemented |
| 32 | Transaction Traceability | Implemented |

## Phase D

Modules: includes/mailer.php; backend/{contact,newsletter}_handler.php; newsletter_*.php.

Evidence: HTTP inquiry/reply preview, confirmation/unsubscribe, phone validation.

| Item | Requirement | Status / qualification |
| --- | --- | --- |
| 33 | Allow Contact Replies | Implemented |
| 34 | Contact Message Statuses | Implemented |
| 35 | Contact Reply Email | Implemented; preview verified, real delivery requires mail configuration |
| 36 | Improve Contact Spam Protection | Implemented |
| 37 | Complete Newsletter Subscription | Implemented |
| 38 | Prevent Duplicate Subscribers | Implemented |
| 39 | Newsletter Statuses | Implemented |
| 40 | Unsubscribe | Implemented |
| 41 | Admin Newsletter Management | Implemented |
| 42 | Standardize Contact Number Validation | Implemented |
| 43 | Remove Validation Conflicts | Implemented |

## Phase E

Modules: backend/profile_handler.php; includes/api_records.php; js/{workspace,notices}.js.

Evidence: HTTP profile escaping, live-session deactivation; browser user editor.

| Item | Requirement | Status / qualification |
| --- | --- | --- |
| 44 | Add/Edit Profile Functionality | Implemented contact editing; existing validated photo upload preserved |
| 45 | Protect Sensitive Changes | Implemented |
| 46 | Fix Deactivation "Invalid JSON Object" | Implemented |
| 47 | Refresh User Status | Implemented |
| 48 | Account Status Management | Implemented |
| 49 | Fix Stuck Messages | Implemented |
| 50 | Add Exit Button | Implemented |
| 51 | Auto-Dismiss Success Messages | Implemented |
| 52 | Notification Center Behavior | Implemented |
| 53 | Prevent Duplicate Notifications | Implemented |

## Phase F

Modules: index.php; includes/{donation_progress,api_reports,api_common}.php.

Evidence: Browser Donate/login and workspace metrics; HTTP CSRF/audit flows.

| Item | Requirement | Status / qualification |
| --- | --- | --- |
| 54 | Add FAQ Section | Implemented |
| 55 | Synchronize FAQ Rules | Implemented |
| 56 | Donate Button Routing | Implemented |
| 57 | Enforce Terms on Backend | Implemented |
| 58 | Frontend + Backend Validation | Implemented |
| 59 | Hide Technical Errors | Implemented |
| 60 | Improve Donor Progress Flow | Implemented |
| 61 | Show Current Donor Status | Implemented |
| 62 | Improve Staff/Admin Dashboard Metrics | Implemented |
| 63 | Improve Audit Details | Implemented |
| 64 | Audit Important Security/Administrative Actions | Implemented |

## Phase G

Modules: js/; css/design.css; css/workspace.css; public PHP pages.

Evidence: PHP/JS syntax; browser navigation, forms, inventory and console inspection.

| Item | Requirement | Status / qualification |
| --- | --- | --- |
| 65 | Remove Duplicate Script Imports | Implemented |
| 66 | Consolidate Reusable JavaScript | Implemented |
| 67 | Prevent Duplicate Form Submission | Implemented |
| 68 | Consistent Action States | Implemented |
| 69 | Improve Error Placement | Implemented |
| 70 | Consistent Status Styling | Implemented |
| 71 | Dashboard Alignment | Implemented |
| 72 | Sidebar Styling | Implemented |
| 73 | Map Alignment | Existing Contact map aligned; no Locations map existed |
| 74 | Clean Up Large CSS Files | Reviewed; large stylesheet retained to preserve design |
| 75 | Reduce Override Accumulation | Targeted map/dashboard overrides consolidated |
| 76 | Fix Cache Busting | Implemented |
| 77 | Keep Local/XAMPP Compatibility | Implemented |

## Phase H

Modules: tests/integration.php; test_runner.php; README.md; docs/.

Evidence: Disposable HTTP/database suite; source and documentation review.

| Item | Requirement | Status / qualification |
| --- | --- | --- |
| 78 | Add/Restore Actual Tests | Automated coverage in tests/integration.php; browser checks supplement it |
| 79 | Authentication Tests | Automated coverage in tests/integration.php; browser checks supplement it |
| 80 | Authorization Tests | Automated coverage in tests/integration.php; browser checks supplement it |
| 81 | Validation Tests | Automated coverage in tests/integration.php; browser checks supplement it |
| 82 | SQL Injection Tests | Automated coverage in tests/integration.php; browser checks supplement it |
| 83 | XSS Tests | Automated coverage in tests/integration.php; browser checks supplement it |
| 84 | Workflow Tests | Automated coverage in tests/integration.php; browser checks supplement it |
| 85 | Correct README | Implemented |
| 86 | Phase 3 Checklist | Implemented |
| 87 | Document Major Workflows | Implemented |

## Verification limits

- Real email acceptance/delivery is not tested; previews are tested and clearly labelled.
- The project has no automated medical testing or clearance; authorized staff record those decisions.
- Legacy Reserved units retain their existing meaning until staff review them.
- No records were deleted, no public hosting was configured and no unrelated CSS redesign was performed.
- Photo-upload rendering and exhaustive device/assistive-technology coverage remain manual QA.
