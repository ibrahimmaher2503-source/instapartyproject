# Confirmed findings — local tester.army audit, 2026-10-12

These are current failures against isolated fixtures, not production observations. Both repro tests failed with `ASSERTION_FAILED` twice: initial run `01a13161-3c80-7d38-ab33-fede881372d4`, and repeat run `01a1316d-2227-782a-994e-cf96d3800a0d` (52.76 seconds, exit 1). They are deliberately outside the normal suite and remain unfixed application defects.

## TA-SYS-001 — Published service returned as unavailable in wishlist

Severity: medium. Save the published sale fixture to `POST /api/v1/customer/wishlist/items`. Creation returns 201 and the correct public service ID, but `data.is_available` is false. The same service is published, stocked and displayable in discovery.

Root cause: `app/Modules/Discovery/Http/Resources/WishlistItemResource.php:24` compares `Service.status` directly to the `ServiceStatus::Published` enum. `Service.php:106` casts the status to `ServiceState`, so the strict comparison fails.

Repro: `tests/tester-army/bugs/wishlist-availability.e2e.ts`. Evidence: `.e2e/system-audit/repros/results/wishlist-availability-published-displayable-service-10555c5fea52f710/trace.md`.

## TA-SYS-002 — Accepted chat report type reaches an unhandled server exception

Severity: medium. An authenticated customer submits `reportable_type=chat_thread`, a valid-format public ULID that does not exist, `reason=other` and local test details. The expected outcome is a client error for the unsupported/missing target. Actual status is **500**, captured by the repeat assertion `expected 500 to be less than 500`.

Root cause: `app/Modules/TrustSafety/Http/Requests/SubmitReportRequest.php:24` accepts `chat_thread`, while `SubmitReportAction.php:17` maps only `vendor_profile` and line 28 throws `InvalidArgumentException` for the accepted chat type. This confirms an input-handling failure, not a successful test of reports against real chat participants.

Repro: `tests/tester-army/bugs/report-type.e2e.ts`. Evidence: `.e2e/system-audit/repros/results/report-type-unknown-chat-report-target-91b3566f01d265d5/trace.md`.

## Rejected or inconclusive explorer findings

- All three products cost EGP 125: fixture data explicitly sets every price to 12,500 minor units; no bug.
- Price-max filtering: the explorer's own actual result says zero products at max EGP 120, contradicting its bug title. Exact bilingual price tests verify this separately.
- Header search blocked while a modal menu is open: expected modal interaction. A dedicated 390px test verifies closing the menu then navigating to search. The initial `mobile` exploration actually selected the desktop target; it is excluded from mobile acceptance.
- Planner had no city under Cairo: the fixture initially generated a city whose region belonged to another governorate. The fixture is corrected; bilingual planner tests verify the linked hierarchy and invalid dates.
- Native required-field validation and incomplete Arabic registration exploration: warning/inconclusive, not confirmed defects. No user was registered by the explorers.

Explorer artifacts remain under `tests/tester-army/.e2e/system-audit/explore/` (the initial nested config made its own directory the runner root); console logs are under `.e2e/system-audit/explore/`. Root-level configs now keep paths consistent and select one target explicitly.
