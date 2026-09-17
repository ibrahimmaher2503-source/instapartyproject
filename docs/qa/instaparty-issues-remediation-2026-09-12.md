# InstaParty issue remediation ledger — 2026-09-12

Source: [instaparty-issues-2026-09-12.md](instaparty-issues-2026-09-12.md)

## Baseline before this remediation pass

- Local only; no deployment or push is authorized.
- Git metadata is unavailable in this checkout: `fatal: not a git repository: (NULL)`.
- Pest: 81 passed, 1028 assertions.
- Chromium admin acceptance: 3 passed.
- Changed-file Pint and PHPStan checks passed.

## Workstreams

| Workstream | Owner | Scope | Status |
|---|---|---|---|
| Storefront and discovery | `storefront_discovery_repairs` | FR-2, FR-4, WEB, storefront localization, FR-5 | REVIEWED |
| Booking and admin negotiation | `booking_admin_repairs` | OPS, booking details, modifications, interventions, state/activity audit | REVIEWED |
| Vendor, catalog, finance and remaining admin operations | `vendor_ops_finance_repairs` | Vendor lifecycle, services, inventory/import, payments/settlement, support/comms and remaining admin UI | REVIEWED |
| Environment and full-E2E blockers | coordinator | BLOCK-BOOKING-TEST-RUNTIME-001 and BLOCK-VENDOR-E2E-001 | PARTIAL |

## Closure rule

A report item becomes `CLOSED` only after its current trigger is reproduced or disproved, the root cause is fixed where needed, and a focused automated or Browser check passes. Items requiring unavailable external services, production data, or a missing product decision remain `BLOCKED` or `NEEDS_DECISION`.

## Results

All three requested Luna/XHigh agents completed their bounded review and repair pass. The coordinator then ran the merged automated and Browser checks.

| Result | Count |
|---|---:|
| Newly closed in this pass | 27 |
| Confirmed already fixed locally | 18 |
| Local test-runtime blocker resolved | 1 |
| Partial; more UAT or product work required | 6 |
| Open or blocked after review | 21 |
| Total source-report records | 73 |

### Newly closed

`BUG-OPS-001`, `BUG-OPS-PERMISSION-001`, `BUG-OPS-002`, `BUG-MOD-003`, `BUG-MOD-004`, `BUG-INTERVENTION-002`, `BUG-STATE-001`, `UX-OPS-001`, `UX-BKG-001`, `UX-MOD-001`, `FR-2`, `FR-4`, `BUG-STOREFRONT-LOCALIZATION-001`, `FR-3`, `WEB-005`, `WEB-011`, `WEB-017`, `WEB-014`, `BUG-SUPPORT-COMMS-DETAIL-001`, `BUG-CHAT-PII-001`, `UX-INBOX-001`, `BUG-NOTIFICATION-TEMPLATE-001`, `BUG-ANALYTICS-EMPTY-001`, `BUG-NOTIFICATION-QUEUE-001`, `BUG-SEARCH-DATA-001`, `BUG-ADMIN-ROUTES-001`, `BUG-VENDOR-ROLE-EDITOR-001`.

### Confirmed already fixed locally

`BUG-BKG-001`, `BUG-BKG-002`, `BUG-MOD-001`, `BUG-MOD-002`, `BUG-INTERVENTION-001`, `WEB-001`, `FR-5`, `VEN-004`, `VEN-005`, `BUG-VENDORQ-001`, `BUG-VREG-SEARCH-001`, `BUG-VPROFILE-001`, `BUG-SERVICE-STATE-001`, `BUG-SALE-DETAIL-001`, `BUG-PAYMENT-DETAIL-001`, `BUG-IDEMPOTENCY-001`, `BUG-LOYALTY-I18N-001`, `BUG-VENDOR-SERVICE-WORKFLOW-001`.

`BUG-BKG-002` is closed for the local checkout because the isolated Filament session test covers clean, admin, vendor, and unverified sessions. Live deployment verification remains outside the authorized local-only scope.

### Resolved local blocker

`BLOCK-BOOKING-TEST-RUNTIME-001`: the isolated test environment now boots and the full Pest suite completes.

### Partial

- `WEB-015`: Arabic/English direction and mobile width pass Browser smoke coverage; the complete Arabic visual sweep is still missing.
- `WEB-012`: purchase-card localization improved; full visual review of the purchase flow is still missing.
- `VEN-009`: the locally provable vendor-list behavior is present; the complete role journey remains unverified in Browser.
- `VEN-011`: the locally provable vendor-flow behavior is present; full operational UAT remains unverified.
- `BUG-MODERATION-SLA-001`: the queue now shows service context and waiting age and sorts oldest first; the product still lacks an approved overdue threshold, escalation rule, moderation history and mandatory decision-reason contract.
- `BUG-CHAT-MODERATION-SLA-001`: the queue now shows waiting age, sorts oldest first and exposes the existing escalation path; assignment/ownership and the approved SLA boundary still require a trust-and-safety decision.

### Open or blocked

`BUG-ACTIVITY-001`, `UX-INTERVENTION-001`, `BUG-DATA-001`, `BUG-VDOC-001`, `BUG-INVENTORY-001`, `BUG-IMPORT-001`, `BUG-WITHDRAWAL-001`, `BUG-WEBHOOK-001`, `BUG-FINANCE-IDENTITY-001`, `BUG-SUPPORT-WORKFLOW-001`, `BUG-SUBSCRIPTION-INTEGRITY-001`, `BUG-VENDOR-BANK-DATA-001`, `UX-VREG-001`, `UX-SERVICE-001`, `UX-MODFIN-001`, `UX-SUPPORT-COMMS-001`, `UX-REMAINING-001`, `VEN-006`, `VEN-007`, `BLOCK-INBOX-001`, `BLOCK-VENDOR-E2E-001`.

`BUG-DATA-001` needs a trusted Arabic source before any stored text is rewritten. `BLOCK-VENDOR-E2E-001` stays open because the complete multi-party supplier-to-settlement journey has not been exercised in a real Browser session.

## Verification after merge

| Check | Result |
|---|---|
| Full Pest suite | PASS — 104 tests, 1116 assertions |
| Focused merged regression tests | PASS — 12 tests, 36 assertions |
| Playwright Chromium | PASS — 9 tests: isolated admin login, admin screens including campaigns and notification dispatches, review/chat moderation queues, subscription/advertising analytics, locale/responsive admin, notification-template preview, search telemetry and Arabic/English storefront |
| Targeted PHPStan | PASS — no errors with a 1 GB analysis limit |
| Targeted Pint | PASS on the merged files |
| Frontend production build | PASS |
| Repository-wide Pint | BASELINE FAIL — many unrelated existing files require formatting; no mass reformat was applied |
| Git status | UNAVAILABLE — `fatal: not a git repository: (NULL)`; Git was not repaired or reinitialized |

The Playwright result is real Browser evidence for the listed screens only. It does not close the full supplier-to-settlement E2E or the remaining role-specific UAT items.

## Continuation — 2026-09-13

- All three Luna/XHigh workers failed to resume because the account usage limit was reached. No new worker result is counted as completed.
- `BUG-NOTIFICATION-TEMPLATE-001` is CLOSED for its reported trigger: template body and subject substitute context variables in the shared dispatch path; missing or non-scalar values create a failed dispatch; create/edit reject undeclared variables and missing Arabic content; and the editor exposes a live preview. The alternative-vendor listener supplies the booking public ULID.
- `BUG-ANALYTICS-EMPTY-001` is CLOSED for its reported trigger: the chart queries now use the database driver's month expression, so the Livewire widgets render on the isolated SQLite Browser runtime while retaining the MySQL expression used by the application runtime.
- `BUG-NOTIFICATION-QUEUE-001` is CLOSED for its local trigger: a scheduled command dispatches due campaigns; campaign dispatch is row-locked and accepts only drafts or due scheduled records; stale queued dispatches are recovered after 15 minutes through the existing audited retry path; and notification jobs atomically claim a queued row so duplicate jobs do not send it twice. The development campaign fixture now reflects its completed run instead of remaining Scheduled.
- `BUG-SEARCH-DATA-001` is CLOSED for its reported trigger: whitespace searches without filters are no longer stored; filters-only searches are explicitly labelled; saved filters render localized product types, labels and Brick Money values instead of raw JSON. Historical records are displayed safely and were not deleted.
- `BUG-ADMIN-ROUTES-001` is CLOSED for the six reported route families: campaigns and notification templates use their existing public ULIDs; category-field schemas receive a generated public ULID through a backfill migration; app settings, feature flags and Shield roles use their unique natural keys. Numeric record binding returns 404. No production migration was run.
- `BUG-VENDOR-ROLE-EDITOR-001` is CLOSED: the Shield resource now resolves the configured project Role model by its unique name; a Browser test loads a Vendor role with two stored permissions, confirms exactly two checked controls, saves, reloads and confirms both permissions remain selected. Numeric role routes return 404.
- Review and chat moderation now expose age and oldest-first priority. They remain PARTIAL because no approved SLA threshold/assignment policy exists in this checkout; none was invented.
- Focused `NotificationTemplateRenderingTest`: PASS — 3 tests, 15 assertions, including EN/AR rendering, missing/invalid values, documented-variable validation and failed-dispatch persistence.
- Targeted PHPStan for `ResolvedTemplate` and `DispatchNotificationAction`: PASS, no errors with a 1 GB limit.
- Focused queue recovery: PASS — 3 tests, 12 assertions for due scheduling, future scheduling, stale dispatch recovery/audit and duplicate-job claiming.
- Focused search telemetry: PASS — 1 test, 3 assertions plus a Browser journey covering filters-only activity, localized product type and formatted EGP amount.
- Focused admin route binding: PASS — 1 test, 12 assertions across all six reported resource families, accepting public/natural keys and rejecting numeric IDs.
- Focused Shield role Browser test: PASS — existing permissions hydrate, survive save/reload and the numeric route returns 404.
- Full Pest rerun: PASS — 104 tests, 1116 assertions (historical result from the prior continuation; superseded by the Wave 3 result below for the current checkout).
- Playwright Chromium: PASS — 9 tests in 48.1 seconds (historical result from the prior continuation; superseded by the Wave 3 result below for the current checkout).
- Laravel schedule inspection: PASS with isolated array cache; both notification recovery (every five minutes) and due-campaign dispatch (every minute) are registered.
- Targeted Pint: PASS on all files changed in this continuation.
- No production or real-data mutation was performed.

## Wave 3 follow-up — 2026-09-13 (Luna/XHigh)

- The three requested bounded workers completed their assigned review passes: `luna_vendor_security`, `luna_support_activity`, and `luna_finance_integrity`. The coordinator did not repair Git, change `.env`, install packages, run production commands, or mutate production/real data.
- Focused follow-up evidence reported by the workers: 10 focused tests / 42 assertions passed; banking masking unit coverage passed 2 tests / 8 assertions. Targeted PHPStan for the 15 changed Resources/Actions passed. These checks do not close the remaining browser or policy-dependent records.
- The earlier explanation that the facade failure was transient is corrected. The sandbox run is a reproducible environment blocker: `realpath(getcwd())` is false, Pest does not load `tests/Pest.php`, and Laravel providers/facades are absent. The outside-sandbox run is the authoritative application result.

### Sequential full verification

| Check | Result | Evidence |
|---|---|---|
| Full Pest (`php vendor/bin/pest --bail`, outside sandbox) | Historical pre-fix FAIL — 48 passed, 1 failed, 835 assertions, 64 pending before bail | `FilamentTranslationCoverageTest`: missing `identity.columns.bank_branch` in EN; superseded by the final rerun below |
| Full Playwright (`npm run e2e`, 1 worker) | Historical pre-fixture FAIL — 10 passed, 2 failed of 12 | Inventory/document fixture and Arabic assertions were repaired; superseded by the final rerun below |
| Playwright inventory failure | OPEN/PARTIAL | `tests/e2e/.results/admin-audit-inventory-rese-e7cc4-es-and-expired-hold-context-chromium/error-context.md`; no seeded `E2E Readable Inventory Service` or `E2E Inventory Customer` was present |
| Playwright document failure | OPEN/PARTIAL | `tests/e2e/.results/admin-audit-vendor-documen-ae0ef-oses-context-before-editing-chromium/error-context.md`; no seeded `E2E Document Vendor` was present |

### Status decisions after follow-up

- `BUG-WITHDRAWAL-001`: OPEN. No financial transition or production withdrawal data was touched; stale pending requests and audit detail still require a safe operational/Browser proof.
- `BUG-WEBHOOK-001`: OPEN/PARTIAL. The display presentation now has explicit signature/processing labels and legacy event formatting, but the source closure contract still requires complete audit persistence, invalid/duplicate/retry coverage, and redaction evidence.
- `BUG-FINANCE-IDENTITY-001`: OPEN/PARTIAL. Identity branch translations, masking and safe document presentation now pass focused checks; reveal, encryption and key-rotation evidence is still missing.
- `BUG-SUBSCRIPTION-INTEGRITY-001`: OPEN. No billing policy decision or data mutation was made.
- `BUG-VDOC-001` and `BUG-INVENTORY-001`: OPEN/PARTIAL after the pre-fix Browser run; their deterministic fixtures and Arabic/English assertions were repaired and the final Browser rerun is recorded below. Inventory concurrency evidence remains unverified, so no full inventory closure is claimed.
- All previously recorded partial/open items remain partial/open unless the evidence above explicitly changes their status. No bank, document, inventory, webhook, or finance item is closed from a partial UI result.

### Deterministic E2E fixture follow-up — 2026-09-13

- `tests/e2e/auth/global.setup.ts` now runs the existing `EgyptGeographySeeder` on the isolated SQLite database and passes the stable Cairo governorate and Nasr City IDs into the vendor profile factory. The inventory service, reservation and vendor document are therefore created without resetting `CountryFactory` identifiers in a new Tinker process. Constraints remain enabled and the application schema was not changed.
- Playwright setup verification: PASS — 1 setup test, 1 passed in 19.0 seconds. No `countries.iso3` unique-constraint error was emitted.
- Direct isolated-database fixture assertion: PASS — `countries=15`, `iso3=EGY` present, inventory service=1, expired reservation=1, document vendor=1, document file=1.
- At the time of this fixture check, the full rerun was waiting on the inventory test owner to correct `$expired`/`$hold` in `tests/Feature/InventoryExpiryTest.php`; the Identity `bank_branch` EN/AR translation was already present. The final rerun after that correction is recorded below.

### Final sequential verification — 2026-09-13

- `tests/Feature/InventoryExpiryTest.php`: PASS outside the sandbox — 4 tests, 17 assertions. Exact expiry, rental/sale/digital paths, atomic cleanup and idempotency pass; real concurrent-hold verification remains unverified.
- Full Pest (`php vendor/bin/pest --bail`): PASS outside the sandbox — 118 tests, 1,177 assertions, 50.70 seconds. This includes the repaired `bank_branch` translation coverage and the four inventory expiry tests.
- Full Playwright (`npm run e2e`, one worker): PASS outside the sandbox — 12 tests, 12 passed, about 1 minute. Inventory and vendor-document journeys pass with the Arabic default locale; EN/AR assertions pass. No `countries.iso3` seed error occurred.
- No Browser failures remained in the final run, so no reassignment was sent. `BUG-VDOC-001` has Browser evidence for the reported context/open surface; `BUG-INVENTORY-001` still keeps the OPEN/PARTIAL status until concurrency and the remaining source-report operational evidence are verified.

### Current integration snapshot — 2026-09-13 (Luna/XHigh)

This section supersedes earlier historical counts for the current checkout. The three active workers are `luna_vendor_security`, `luna_support_activity` and `luna_finance_integrity`; all checks below ran locally against isolated test data only.

| Check | Current result | Evidence or limitation |
|---|---|---|
| Webhook focused regression and presentation | PASS — 13 tests, 60 assertions | Invalid/missing signature reason, malformed parser failure, unknown-reference redaction, sequential event spelling/idempotency, duplicate display and PCI/PII redaction are covered. Concurrent duplicate delivery and the full gateway contract remain UNVERIFIED. |
| Excel import focused tests | PASS — 6 tests, 57 assertions | Failure sanitization, bilingual row errors, empty files, valid counters and three product types pass. |
| Support focused tests | PASS — 3 tests, 7 assertions | Permission-gated transitions and existing assignee detail pass; schema/contract for replies, history, SLA and booking links is absent. |
| Inventory focused tests | PASS — 4 tests, 17 assertions | Exact expiry, rental/sale/digital cleanup and idempotency pass; real concurrent-hold verification remains UNVERIFIED. |
| Limited Browser acceptance | PASS — 3 feature tests plus setup | Import list/detail context, support ticket detail context and safe webhook status labels pass. The import detail page does not render the persisted error-row EN/AR messages, so that surface remains PARTIAL. |
| Full Pest (`php vendor/bin/pest --bail`) | PASS — 131 tests, 1,262 assertions, 47.91 seconds | Outside sandbox; includes the current webhook, import, support and inventory tests. |
| Full Playwright (`npm run e2e`) | PASS — 15 tests, one worker, 1.3 minutes | Setup, admin, storefront, import, support and webhook screens pass. No `countries.iso3` seed error occurred. |
| Targeted Pint | PASS | Payments files and focused regression files. |
| Targeted PHPStan | FAIL — 2 existing findings | `Payment::$amount_minor` and `Payment::$amount_currency` remain undefined in `ProcessPaymobWebhookAction.php`; no suppression or baseline change was added. |

Current issue decisions remain:

- `BUG-WEBHOOK-001`: OPEN/PARTIAL. Bounded processing-state, idempotency and redaction paths are covered; retry ordering, concurrent delivery and the complete source closure contract remain unverified.
- `BUG-IMPORT-001`: OPEN/PARTIAL. Focused tests and list/detail context pass, but the Browser detail omits persisted error-row messages; Catalog implementation follow-up is required.
- `BUG-SUPPORT-WORKFLOW-001`: OPEN/PARTIAL. Focused tests and the existing detail context pass; no approved reply/history/SLA/schema contract is present.
- `BUG-WITHDRAWAL-001`: OPEN/UNVERIFIED. No financial transition or withdrawal data was touched.
- `BUG-FINANCE-IDENTITY-001`: OPEN/PARTIAL. Identity branch/masking/document presentation evidence exists; reveal, encryption and key-rotation evidence is absent.
- `BUG-SUBSCRIPTION-INTEGRITY-001`: OPEN/UNVERIFIED. No billing policy decision or data mutation was made.
- `BUG-INVENTORY-001`: OPEN/PARTIAL. Local expiry behavior passes; concurrent-hold and remaining operational evidence are absent.

The historical source-report totals remain 73 records: 27 newly closed, 18 confirmed already fixed, 1 resolved local runtime blocker, 6 partial, and 21 open or blocked. This integration run changes evidence for the listed items without closing any contract-dependent or concurrency-dependent record. Git remains unavailable (`fatal: not a git repository: (NULL)`); no production, `.env`, package, migration or ledger data was changed.

### Latest integration snapshot — 2026-09-13 (Wave 5, Luna/XHigh)

This is the latest current-checkout evidence and supersedes earlier current
snapshot counts where the same check is listed. All data remained local and
isolated; no production, `.env`, package, migration or ledger data was changed.

| Check | Current result | Evidence or limitation |
|---|---|---|
| Withdrawal focused regression | PASS — 2 tests, 6 assertions | Normal rejected audit detail and malformed EN/AR payment-note values return HTTP 200. The pre-fix malformed value reproduced HTTP 500 from a `TypeError`; the fix is presentation-only. |
| Limited withdrawal Browser acceptance | PASS — setup plus 1 feature test | Rejected 650 EGP fixture opens in the admin audit detail, status is visible, full IBAN and malformed note text are absent. |
| Full Pest (`php vendor/bin/pest --bail`) | PASS — 139 tests, 1,289 assertions, 106.82 seconds | Outside sandbox; includes current data, activity, inventory, webhook and withdrawal regressions. |
| Full Playwright (`npm run e2e -- --timeout=120000 --workers=1`) | PASS — 16 tests, 3.7 minutes | Includes withdrawal, inventory/document, import, support, webhook and storefront checks. The first run stopped before feature tests because setup exceeded the default 30-second timeout; the rerun completed with the explicit timeout. |
| Targeted Pint | PARTIAL | `Payment.php` and the focused withdrawal test pass. Existing `WithdrawalResource.php` formatting reports two fixers (line ending and import order); no broad reformat was applied. |
| Targeted PHPStan | PARTIAL — 13 existing findings remain | The real `Payment::$amount_minor` and `$amount_currency` findings are removed with model PHPDoc. Remaining findings are Payment model generic/scope typing; no ignores or baseline entries were added. |
| Activity owner focused result | PASS — 3 tests, 7 assertions | Permission and presentation checks pass; intervention contract/SLA remains PARTIAL. |
| Data owner result | PARTIAL | Safe English fallback for literal-question-mark legacy values covers the reported 11/83 affected records; broader finance identity evidence remains UNVERIFIED. |

Current status decisions:

- `BUG-WITHDRAWAL-001`: **OPEN/PARTIAL**. The audit-detail 500 caused by malformed localized payment-note data is fixed and covered in focused, Browser and full-suite runs. Stale pending requests, dual-control/payout operations, concurrent requests, bank-policy verification and the complete multi-party operational contract remain unverified, so the issue is not closed.
- `BUG-ACTIVITY-001`: **OPEN/PARTIAL**. The owner result covers the authorized presentation path; intervention/SLA evidence is still absent.
- `BUG-DATA-001`: **OPEN/PARTIAL**. The safe English fallback is covered for the reported legacy values; the remaining source-data decisions require trusted evidence.
- `BUG-FINANCE-IDENTITY-001`: **OPEN/UNVERIFIED**. The remaining reveal, encryption and key-rotation evidence is absent.
- `BUG-WEBHOOK-001`, `BUG-IMPORT-001`, `BUG-SUPPORT-WORKFLOW-001` and `BUG-INVENTORY-001` retain their prior OPEN/PARTIAL statuses because full UI passes do not establish missing contracts or concurrent behavior.

The historical source-report totals remain 73 records: 27 newly closed, 18 confirmed already fixed, 1 resolved local runtime blocker, 6 partial, and 21 open or blocked. Wave 5 adds evidence without closing any contract-, policy- or concurrency-dependent record. Git remains unavailable (`fatal: not a git repository: (NULL)`).

### Latest integration snapshot — 2026-09-13 (Wave 6, Luna/XHigh)

This is the latest current-checkout snapshot for the Wave 6 UI-finance slice.
It supersedes earlier current-check counts where the same check is listed.
All data and Browser fixtures remained local and isolated; no production,
`.env`, package, migration or ledger data was changed.

| Check | Current result | Evidence or limitation |
|---|---|---|
| Finance/moderation focused Pest | PASS — 2 tests, 16 assertions | Arabic title, review type/locale/status, refund status/reason/note and Brick Money output are covered. |
| Limited Finance Browser acceptance | PASS — 2/2 | Real Chromium setup plus the Arabic moderation/dispute journey; raw `customer_request` is absent. |
| Targeted Pint | PASS | Review page, dispute page and focused test passed. |
| Targeted PHPStan | BLOCKED_BY_CONFIG | Filament paths are excluded by `phpstan.neon`; direct invocation returned `No files found to analyse`. Existing 13 findings remain documented and unsuppressed. |
| Full Pest (`php vendor/bin/pest --bail`) | PASS — 149 tests, 1,330 assertions, 61.82 seconds | First run stopped at translation coverage; both locale files were completed, the coverage test passed, and the full rerun passed all current tests. |
| Full Playwright (`npm run e2e -- --timeout=120000 --workers=1`) | PASS — 17 tests, 1.2 minutes | Fresh isolated setup plus all admin, storefront, import, support, webhook, inventory/document and Arabic finance/moderation journeys passed. |
| Final targeted Pint | PASS | Wave 6 page, translations and focused test passed. |

Current status decisions:

- `UX-MODFIN-001`: **OPEN/PARTIAL**. The targeted Review Moderation and Dispute Oversight presentation defects are fixed and covered. Full finance-screen coverage, responsive/empty/loading states, diagnostic event views, concurrent behavior and missing contracts remain unverified.
- `UX-SERVICE-001`: **OPEN/UNVERIFIED** for this wave; Catalog import/service pages were outside the selected scope.
- `BUG-FINANCE-IDENTITY-001`: **OPEN/PARTIAL**. Finance identity labels and safe legacy fallback now have both-locale translations, but reveal/encryption/key-rotation evidence remains absent.
- `BUG-WITHDRAWAL-001`, `BUG-WEBHOOK-001`, `BUG-IMPORT-001`, `BUG-SUPPORT-WORKFLOW-001` and `BUG-INVENTORY-001` retain their prior OPEN/PARTIAL statuses; this UI slice does not close their contracts or concurrency gaps.

The historical source-report totals remain 73 records: 27 newly closed, 18 confirmed already fixed, 1 resolved local runtime blocker, 6 partial, and 21 open or blocked. Full-suite validation for that 2026-09-13 slice is complete; contract, policy and concurrency gaps remain explicitly open or unverified above.

## Continuation — 2026-09-15 custom Chromium evidence

- Custom Chromium passed **2 tests in 2.7 minutes**. In one same-vendor/service/customer journey, the vendor documents and service gallery for `01M2GZJYW7YA6QDS50Y5EZ7MF3` were uploaded through the UI and admin published the service; booking `01M2GZM23V4FFHA86QV2Z0CMCM` was submitted and accepted by that vendor, reaching `confirmed`.
- Payment `01M2GZM5043594KGGK6SAVP49K` returned HTTP 201 with `pending`; the booking remained `unpaid`.
- This adds focused Browser evidence only. Capture/webhook/paid, the reported Edit 403, and multi-party settlement/withdrawal/reconciliation remain open. Full suites are pending; no suite-wide status is inferred from this run.

## Continuation — 2026-09-15 notification listeners and Browser diagnosis

- `OnServicePublished` and `OnServiceRejected` no longer reference the undefined `vendorProfile`; each loads `vendor.user` from the service and dispatches to that owning user, returning safely when the user is absent.
- Focused Pest: **PASS — 5 tests, 17 assertions**. Targeted Pint: **PASS**. Targeted PHPStan: **PASS**. Full Pest: **PASS — 177 tests, 1,701 assertions**.
- Full Playwright remains **PARTIAL**: 1 setup passed and the journey failed during the second vendor-document approval. The ancestor `[role=dialog]` selector found no node, though the visible `.fi-modal-window` contained the expected prompt and Confirm control; `mountTableAction` returned HTTP 200 without errors. The diagnosis indicates a selector/modal mismatch, not proven action failure. No full rerun followed; do not claim full Browser acceptance.
- The custom purchase-chain evidence above is unchanged. Capture/webhook/paid, Edit 403, settlement/withdrawal/reconciliation remain open.
