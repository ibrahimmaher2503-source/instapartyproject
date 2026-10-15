# Admin operations verification

## Scope

This evidence covers the admin booking monitor export and queue age, the selected vendor approval action, and the booking conversion funnel. It does not claim hosted or production verification.

## Behavior

- The booking monitor exports the active filtered and sorted query as an Excel workbook. Its explicit field whitelist is public booking ID, reference number, lifecycle and payment status, total in integer minor units with its currency, submission time, and event start/end times. Names, contact details, addresses, vendor data, bank data, and internal IDs are excluded. Export dates use ISO 8601 UTC values.
- The monitor shows age from `submitted_at`, falling back to `created_at`, and defaults to the oldest submission first. Existing negotiation response deadlines and urgency remain the only SLA signals.
- Vendor approval, withdrawal, report review, and support queues each have an independent editable 48-hour target. Admins can set each target from 1 to 720 whole hours; absent or invalid stored values safely use 48. Active rows show age, a deadline, and overdue/within-target status from the existing request or creation timestamp. Terminal rows are marked inactive, and actionable work sorts oldest first. Deadlines convert from UTC into the admin viewer's timezone. The booking negotiation monitor is not assigned this response SLA.
- Selected vendor approval calls the existing `ApproveVendorProfileAction` once per selected profile. The action retains its permission check, row lock, pending-state check, and eligibility evaluation. The result reports a public profile ID and outcome for each selection; a failure does not roll back an independently successful approval.
- The dashboard funnel uses a seven-calendar-day UTC submission cohort. Its denominator includes bookings with `submitted_at` in that window and an actual `draft -> vendor_review` transition timestamp in the same window. Confirmed, active, and completed counts use distinct bookings with recorded transitions at or after submission through the current time. Conversion percentages use tracked submissions as the denominator. Transition coverage compares tracked submissions with all records whose `submitted_at` is in the window; missing history is surfaced, and percentages are unavailable for an empty cohort.

## Automated evidence

`tests/Feature/QaVerifyAdminOperationsTest.php` covers mixed bulk approval outcomes and role visibility, filtered export-query behavior and its exact public-field whitelist, booking-monitor queue age and oldest-first ordering, funnel stage counts and transition coverage, and the empty-cohort state. `tests/Feature/QaVerifyAdminQueueSlaTest.php` covers independent editable targets, the 1–720-hour bounds and 48-hour fallback, overdue boundary behavior, visible queue fields, active versus terminal states, and oldest-first ordering on all four queues.

Focused validation run in the local worktree:

```text
php artisan test --compact tests/Feature/QaVerifyAdminOperationsTest.php
Tests: 7 passed (50 assertions)

php artisan test --compact tests/Feature/QaVerifyAdminQueueSlaTest.php
Tests: 9 passed (87 assertions)
```

The earlier browser run `npx playwright test tests/e2e/qa-verify-admin-operations.spec.ts --project=chromium --no-deps --output storage/qa-results/admin-operations-fourth` passed before the four-queue SLA browser steps were added: 1 Chromium test in 27.0 seconds (test body 16.1 seconds). That run covered the dashboard funnel/coverage, booking-monitor oldest-first queue and age, an Excel download and mixed bulk approval. The expanded spec adds withdrawal, report, and support queues plus SLA assertions. Its first run reached the dashboard but the saved admin locale was Arabic while the funnel assertion expected English labels; the failure context showed the funnel in Arabic. Every English admin visit now explicitly requests `?lang=en`, with the semantic funnel and conversion assertions unchanged. Parent-run browser verification of that expanded spec is pending. Apply the additive queue-settings migration to the existing disposable E2E database before running so the editable settings are present; do not reset that database. Fixtures restore pre-existing direct permission grants and remove only their own records. Earlier attempts exposed selector/pagination issues; the current test uses semantic rows, a queue search, and synthetic email identifiers because names are truncated in the table.

Independent review found a missing outer polymorphic type constraint in funnel stage counts. The query now constrains transitions to Booking and its feature fixture includes an unrelated User transition with the same numeric ID, which must not increase the completed count. The combined notes and operations tests passed: 12 tests, 67 assertions.

## Limits

Issue #42 was closed at the owner's request for the initial finance/operations export on Bookings Monitor. The successful earlier Chromium run downloaded an Excel workbook; fresh `php vendor/bin/pest tests/Feature/QaVerifyAdminOperationsTest.php --bail` passed 7 tests / 50 assertions in 4.92 seconds (`storage/logs/qa-operations-closure-check.txt`). This does not claim exports on every admin list. The later expanded SLA spec failed while preparing additional fixtures, before export interaction; SLA and broader bulk-action/analytics issues remain open for their unfinished cases.

Feature checks used in-memory SQLite and browser checks used the disposable `storage/e2e.sqlite`. Final broader batch gates and publication remain pending. No live service, external provider, hosted environment, or production state was changed or verified.
