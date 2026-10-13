# Medium-only batch04

Branch: codex/fix-issues20-01a133b7-medium-04.
Base: 04cdd9e7fa3bb9e243931fc00a184f002ca74f8c (main after PR179).

The owner requested a Medium-only implementation batch in parallel, followed by source review. Live GitHub inventory contains twelve Medium reports; no other priority is included to fill twenty tickets.

| Issue | Scope |
|---|---|
| #177 | Explicit vendor approval eligibility and action feedback |
| #175 | Vendor in-app notice for admin booking cancellation |
| #171 | Vendor in-app notice for new pending booking requests |
| #140 | Visible Trust Badge validation for hidden Arabic fields |
| #138 | Saved delivery and event details in customer booking view |
| #136 | Unambiguous booking confirmation with reference and detail link |
| #135 | Visible recipient phone requirement and validation |
| #132 | Customer in-app templates and core lifecycle dispatch |
| #131 | Vendor approval SLA escalation and new support inbox alert |
| #130 | Subscription invoice/payment provenance and correct future fixture/source creation |
| #129 | Withdrawal approval details, history and explicit timezone |
| #85 | Expired response lifecycle and accepted-only fulfillment guards |

Eight implementers own separate domains. Shared registration and translations are coordinated. Independent source reviews follow implementation and corrections precede publication.

Behavioral tests, browser runs and builds are deferred by owner instruction. Regression tests are prepared source only. Formatting and configured static analysis are publication gates. No production credentials, user data, historical financial records, real payments, provider sends, migrations or service changes are authorized by this batch. Historical billing correction and live scheduler acceptance may keep individual issues open; source publication does not establish deployed acceptance.

## Implementation and subsequent review

All twelve selected reports have been investigated and their source scope assembled. Customer submission redirects to its owned booking with a reference and explicit confirmation; saved address, recipient, city, guest count and event end are visible. Recipient phone format requirements and field errors remain visible during wizard hydration. Approval uses explicit domain-validation feedback rather than an eligibility-disabled action that could silently stop after a stale confirmation.

Customer core lifecycle events and vendor request/cancellation events receive insert-only bilingual InApp templates. Durable recipient/event dispatch protection preserves distinct refunds and vendor allocations. Vendor messages use the existing Filament database format and owned allocation links. New support tickets route after commit; vendor SLA rules match their configured event priority, escalate the current alert once, preserve assignment and do not repeatedly reset read/resolved state. Deleted profiles and ineligible inbox recipients are excluded.

Trust Badge create/edit stores the explicit EN/AR map and marks invalid language tabs. Fulfillment actions share an accepted-allocation guard; pending detail controls cannot imply fulfillment is available. Withdrawal view shows approver, approval time and permission-gated history in an explicit viewer timezone. Development subscription fixtures use the selected plan amount/currency and matching seed payment/audit; they run only in local/testing and never rewrite existing subscription or financial evidence.

Independent post-implementation reviews cover approval, storefront, notifications, admin routing, financial views/fixtures and Trust Badge/fulfillment. Review corrections include the real mounted Filament stale-eligibility path and hidden city synchronization after changing governorate following a rejected phone submission. A queued vendor request must also re-check its allocation before delivery to avoid asking for a response after cancellation or timeout. Source review does not prove executed regression tests or browser behavior.

## Issues retained open

- #130: the historical Paid EGP99 invoices, missing matching captures and missing Premium invoice require original subscription/period/price or discount provenance and genuine payment evidence. The existing production subscription capture listener is a stub. This batch fixes development fixture creation only; it does not invent billing or backfill paid history.
- #85: existing timeout action, enabled configuration, registered command and minute schedule are present, and fulfillment guarding is fixed. Affected production bookings still need deployment, active scheduler proof and a post-deployment timeout retest. No production state or scheduler was accessed.

The source-only closure scope is #177, #175, #171, #140, #138, #136, #135, #132, #131 and #129. Migrations are prepared but unrun; deployed template/routing installation, queued delivery and browser acceptance remain separate.

## Final publication gates

All six independent source review scopes approved after corrections, with no remaining concrete introduced high/medium finding. Obsolete vendor requests use the explicit terminal Suppressed status with bilingual labels; they do not count as sent or delivered and cannot enter failed/queued retry paths. The existing adapter insertion/status-update crash window is unchanged; source event protection establishes dispatch creation deduplication, not verified delivery exactly once.

Scoped native Pint passed59 changed PHP source paths (exit0). Full configured PHPStan2G reported [OK] No errors (exit0). Git whitespace and sensitive-path checks passed. The exact66-path publication manifest includes this ledger and itself. PHPStan excludes module Filament paths; independent source review and prepared tests do not substitute for actual panel interaction.

No Pest, frontend/browser test, build, server start, migration, real gateway request, external message or production action ran. #130 and #85 remain open with the explicit acceptance gates above.
