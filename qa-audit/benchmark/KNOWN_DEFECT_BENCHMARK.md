# Known-defect detection benchmark — five-case pilot

Date: 2026-10-12. Local isolated InstaParty checkout only. **STOPPED AFTER FIVE DISTINCT CASES.** No product code, expected business results, production data, deployment, commit or push was changed.

## Result and interpretation

| Case | Previously documented record | Pilot classification | Current evidence |
| --- | --- | --- | --- |
| B01 | TA-SYS-001 — Wishlist availability | DETECTED | A persisted, published sale service returns 201 with `is_available=false`; the correct-availability assertion fails. |
| B02 | TA-SYS-002 — Chat report type | DETECTED | Accepted-format `chat_thread` input returns 500, with no report row; the client-error assertion fails. |
| B03 | VTSE-001 — Cross-vendor resubmit | NOT_REPRODUCIBLE | Attacker gets 404; foreign ownership, price, lifecycle, open change request and pending item remain unchanged; no attack idempotency row. |
| B04 | VTSE-007 — Capture amount/currency mismatch | BLOCKED | Real HTTP handler stops at MySQL interval SQL unsupported by SQLite before the amount/currency guard. Payment remains pending and ledger unchanged, but the intended guard is not reached. |
| B05 | BUG-VENDOR-BANK-DATA-001 — Default bank exposure | NOT_REPRODUCIBLE | Admin edit shows `EG00••••001`, no full fake IBAN in DOM HTML, and the stored value remains unchanged. Limited to this edit/default-IBAN branch. |

Two current defects were detected, two historical triggers did not reproduce in the tested branches, and one case was blocked. No case was classified NOT_DETECTED or finally INCONCLUSIVE. **Do not calculate a 2/5 detection rate:** two cases do not show a current defect, and the blocked case has unknown persistence. This deliberately selected pilot cannot estimate comprehensive coverage or detector sensitivity.

The prior campaign did **not** miss B01/B02 overall: its separate repro suite already detected and reported both. Its **passing normal suite** omitted their decisive business assertions. B03/B05 cannot be called previously missed current bugs because their tested triggers now fail safely. B04 exposes an environment/coverage gap, not proof that the historical financial defect persists. The premise is therefore only partly supported. See [root-cause analysis](MISSED_DEFECT_ROOT_CAUSES.md).

Classification rules:

- DETECTED: the documented conditions exist and the exact intended-outcome assertion fails for the documented defect.
- NOT_DETECTED: a current defect is independently established, but the benchmark detector misses it. A missing old test is recorded separately, not used to inflate this count.
- NOT_REPRODUCIBLE: the specified historical trigger was exercised, decisive business and persistence assertions passed, and the incorrect behavior was not observed. Applies only to the tested branch.
- BLOCKED: environment/setup prevents reaching the business oracle. A failing test alone is not detection.
- INCONCLUSIVE: evidence/expectations/conditions are insufficient for a conclusion. Initial B01 selector ambiguity fell here until corrected; its final verdict uses the successful rerun reaching the unchanged availability assertion.

## Inventory and source reconciliation

[Full inventory and source-bound reproduction designs](DEFECT_INVENTORY_AND_PLANS.md) preserve **181 tracking records**, not 181 independent confirmed defects:

| Source family | Records | Interpretation |
| --- | ---: | --- |
| Original 2026-09-12 issue report | 73 | Source itself separates 48 repair items, 17 design items, 3 blockers and 5 verification items; aliases are not merged. |
| Supplier-to-settlement forensic audit | 27 | 25 original findings plus two product-policy decision records. |
| 2026-10-04 developer handoff | 42 | Named tickets include changes/decisions as well as reported defects; completion claims are historical. |
| Admin audit closure | 12 | Closure-only IDs; unavailable original incorrect behavior is marked unknown. |
| Public UI audit closed-defect bullets | 14 | No original IDs; LOCAL-UI IDs are benchmark source locators. |
| Prior tester.army confirmed findings | 2 | Positive controls, already detected by the previous repro campaign. |
| Seven client requirements | 7 | Presentation/change requests, not automatically product defects; LOCAL-CLIENT locator IDs. |
| Referenced-only requirements | 4 | FR-14/16/17/18 have no independent original description recovered here; not invented as bugs. |

Sources include [original issues](../../docs/qa/instaparty-issues-2026-09-12.md), [remediation history](../../docs/qa/instaparty-issues-remediation-2026-09-12.md), [forensic audit](../../docs/qa/vendor-to-settlement-e2e-audit-2026-09-12.md), [handoff](../../docs/qa/instaparty-developer-handoff-backlog-2026-10-04.md), [prior campaign](../../docs/qa/tester-army-system-execution-2026-10-12.md) and its [confirmed findings](../../docs/qa/tester-army-system/confirmed-findings.md). Wave 4–8 reports are linked to the records they mention in the inventory, rather than silently promoted into fresh passes.

The [scenario catalog](../../docs/qa-discovery/catalog_data.json) contains 1,292 scenarios, 520 flows and 31 proposed scenarios. [Discovery gaps](../../docs/qa-discovery/Discovery_Gaps.md) contains 145 static questions, explicitly not a bug register. [Finance forensic discovery](../../docs/qa-discovery/finance.json), [booking/catalog discovery](../../docs/qa-discovery/booking_catalog.json) and [independent reconciliation](../../docs/qa-discovery/independent_review.json) establish entry points and gaps, not runtime correctness. Scenario references below are traceability links; an adjacent scenario is not exact coverage.

Non-pilot records remain UNKNOWN for current persistence. Their source-bound plans preserve reported triggers and expectations; missing exact data, selectors, side-effect expectations and approval are stated as unresolved gates. This is an exhaustive source inventory, **not 181 ready-to-run executable tests**. No additional defect case was executed.

Expectation conflict retained: historical FR-2 requests a mandatory event end, while later WIZ-01 records an accepted optional end. The old expected result was not rewritten to current behavior; that non-pilot case needs authority/date reconciliation before benchmarking. Similarly, missing original IP-QA observations cannot be reconstructed from a PASS label.

## Environment, data and documentation

- tester.army e2e 0.19.0 / @e2e-dev/web 0.14.0 / Chromium 153; deterministic browser interactions and exact assertions. No standalone Playwright suite was run.
- Official installed skill: [e2e skill](../../.agents/skills/e2e/SKILL.md), [writing tests](../../.agents/skills/e2e/references/writing-tests.md), [bug bash](../../.agents/skills/e2e/references/bug-bash.md), [running](../../.agents/skills/e2e/references/running.md). The skill distinguishes `ASSERTION_FAILED` from locator/setup errors, supports app-only API tests, and requires separate failing repros. Public documentation entry: [tester.army quickstart](https://e2e.tester.army/docs/quickstart).
- Dedicated [benchmark config](../../e2e.benchmark.config.ts) selects only the pilot directory. The normal config excludes it. The PHP case is outside the normal PHP suite directories.
- Existing fixture/launcher uses a guarded `.e2e/app/run-*/database.sqlite`, random credentials and no `.env` copy. The [benchmark seed](../../tests/tester-army/benchmark/seed.php) is loaded only with `TESTER_ARMY_BENCHMARK=1`, after the parent disposable-database guard. No real bank or user data is used.
- The browser/API cases have separate fixture entities and can run independently with `--grep B01`, etc. They share a disposable app per selected run, but do not rely on another case's mutation. The [state reader](../../tests/tester-army/benchmark/state.py) restricts its path and opens SQLite in read-only mode.
- The PHP integration case uses `RefreshDatabase` and asserts SQLite `:memory:`. Only gateway signature acceptance/parsing is substituted; HTTP, idempotency, capture and ledger bindings are real. This does not test Paymob HMAC/transport correctness. No SQL workaround, production-like gateway or guard mock was introduced to make it pass.
- Password entry suppresses some browser pixels by the runner's secret policy; no visual screenshot acceptance is claimed. Runtime traces contain the actual interactions and assertions. Sanitized retained evidence contains only disposable values.

## Independent pilot reproduction plans

### B01 — TA-SYS-001

Original: [prior confirmed findings](../../docs/qa/tester-army-system/confirmed-findings.md#ta-sys-001--published-service-returned-as-unavailable-in-wishlist). Roles/modules: customer; Discovery/Catalog/Identity. Related scenario: **SC-Discovery-01fcf4441a**, save-to-wishlist flow; it does not explicitly cover every response-field semantic.

1. Preconditions: verified customer; published sale service with detail row, stock 20 and price 12,500 EGP minor units; no existing saved item in fresh fixture.
2. Database: approved owner/vendor, published service and empty wishlist. Credentials remain ignored. Snapshot the saved service status and item count.
3. Actor: isolated customer, not admin/vendor.
4. Browser: sign in via `/en/auth/login`; after adding, open `/en/account/favorites`, assert the exact saved product heading; open its service URL and assert exact identity/name.
5. API: `POST /api/v1/customer/wishlist/items` with that public service ID.
6. Expected: 201, a persisted saved item, service still published, `is_available=true`.
7. Incorrect: same item/service succeeds but returns `is_available=false`.
8. Assertions: exact status, saved count 1, SQLite service status `published`, browser product identity and boolean availability. The business boolean is unchanged from the original repro.
9. Persistence: guarded read-only SQLite inspection before evaluating the failing availability assertion.
10. Effects: no service lifecycle change, booking or payment; saved item is confined to disposable customer data. No assertion is made about untested sold-out/unpublished cases.

Repro: [pilot test](../../tests/tester-army/benchmark/pilot.e2e.ts); retained [B01 evidence](evidence/B01.json); [final trace](../../.e2e/system-audit/benchmark/final-controls/results/pilot-b01-ta-sys-001-published-782f78f038e7df19/trace.md). Current persistence: confirmed. Root cause: [WishlistItemResource:24](../../app/Modules/Discovery/Http/Resources/WishlistItemResource.php#L24) compares a `ServiceState` object to a `ServiceStatus` enum.

### B02 — TA-SYS-002

Original: [prior confirmed findings](../../docs/qa/tester-army-system/confirmed-findings.md#ta-sys-002--accepted-chat-report-type-reaches-an-unhandled-server-exception). Roles/modules: customer; TrustSafety/Communication. Related **SC-TrustSafety-778af0c3d0** is the adjacent vendor-profile report flow; the accepted chat-type branch is absent from that scenario's stated outcome.

1. Preconditions: authenticated customer; valid-format, nonexistent chat public ULID; input passes the request's allowed-type/format/enum constraints.
2. Database: zero reports; no chat thread matching `01ARZ3NDEKTSV4RRFFQ69G5FAV`.
3. Actor: isolated customer token obtained through the real login endpoint.
4. Browser: none required for this API-only type mapping; do not claim chat UI coverage.
5. API: POST `/api/v1/customer/reports` with `reportable_type=chat_thread`, that ULID, `reason=other`, disposable details.
6. Expected: a client error (400–499) for unsupported/missing target; no report row.
7. Incorrect: unhandled exception/status 500.
8. Assertions: lower/upper status bounds and unchanged report count; 422 from an empty request would not exercise this case.
9. Persistence: compare report count 0 → 0 before the final status assertion.
10. Effects: no report/moderation workflow is successfully created. Notification/event emission was not independently instrumented.

Repro: [pilot test](../../tests/tester-army/benchmark/pilot.e2e.ts); [B02 evidence](evidence/B02.json); [trace](../../.e2e/system-audit/benchmark/e2e/results/pilot-b02-ta-sys-002-unknown-chat-bb33d270da60c6bc/trace.md). Current persistence: confirmed. [Request:24](../../app/Modules/TrustSafety/Http/Requests/SubmitReportRequest.php#L24) accepts a type missing from [Action mapping:17–28](../../app/Modules/TrustSafety/Application/Actions/SubmitReportAction.php#L17).

### B03 — VTSE-001

Original: [forensic finding](../../docs/qa/vendor-to-settlement-e2e-audit-2026-09-12.md#vtse-001--p0--cross-vendor-service-resubmission-and-unrestricted-mass-assignment). Roles/modules: attacker vendor, owner vendor, admin requester; Catalog/Identity/Shared. Related **SC-Catalog-63ee5055a4**, **SC-Catalog-f7413ff5b6**.

1. Preconditions: two distinct approved vendors; attacker has published rental/sale/digital type approvals; foreign rental service in `changes_requested` with an open change request.
2. Database: service owned by vendor B, price 23,456, one pending requested `base_price_minor` item; no attack-key idempotency record.
3. Actor: vendor A; real login and 200 from own subscription contract establish a valid approved-vendor API context. Route and ownership guard are traced in current source.
4. Browser: none required for the original API exploit; prior index visibility is not an exploit test.
5. API: POST `/api/v1/vendor/services/{foreign-public-id}/resubmit`, changed fields set owner to attacker and price to 1, with unique `benchmark-foreign-resubmit` key.
6. Expected: non-enumerating 404; foreign ownership, price and lifecycle unchanged.
7. Incorrect: foreign mutation/resubmission or ownership transfer.
8. Assertions: exact 404, before/after service equality, correct owner, change request `open`, item `pending`, zero attack idempotency records.
9. Persistence: direct read-only checks prove rejected mutation did not alter protected rows.
10. Effects: no resubmitted lifecycle or idempotency write. Events/notifications were not independently captured. The owner-safe-allowlist branch and all type variants remain untested here.

Repro: [pilot test](../../tests/tester-army/benchmark/pilot.e2e.ts); [B03 evidence](evidence/B03.json); [trace](../../.e2e/system-audit/benchmark/focused/results/pilot-b03-vtse-001-foreign-service-94318499541eda1c/trace.md). Current persistence: not reproduced in this branch. Existing [CatalogSecurityRegressionTest](../../tests/Feature/CatalogSecurityRegressionTest.php#L17) already tests the 404/persistence guard; the historical fix is corroborated, not assumed.

### B04 — VTSE-007

Original: [forensic finding](../../docs/qa/vendor-to-settlement-e2e-audit-2026-09-12.md#vtse-007--p1--captured-webhook-amount-is-not-reconciled-with-the-payment). Roles/modules: system/gateway; Payments/Booking/Settlement/Shared. Related **SC-Payments-3436fcba08** and adjacent **SC-Payments-929e770a5e**.

1. Preconditions: confirmed, unpaid booking; pending payment of 10,000 EGP minor units; valid-signature capture event with a different amount/currency; no prior callback key.
2. Database: isolated SQLite memory database, no capture ledger group or timestamp. Snapshot ledger count and booking/payment state.
3. Actor: simulated authenticated gateway callback, no user authorization bypass claim.
4. Browser: none; webhook/accounting side effects require HTTP/integration checks, not screen visibility.
5. API: POST `/api/v1/webhooks/paymob` with simulated verified signature/parser DTO for 9,999 EGP; planned second variant 10,000 USD. All downstream services are real.
6. Expected business result: mismatch handling is reached, payment remains pending, booking unpaid, no captured timestamp/group or new ledger entries. A 200 acknowledgment alone would not prove this.
7. Incorrect historical result: local amount is captured/ledgered despite gateway mismatch.
8. Assertions: acknowledgment as transport progress, exact state, null capture timestamp/group, unchanged ledger count and mismatch processing reason. The branch must be reached before a NOT_REPRODUCIBLE verdict is possible.
9. Persistence: first attempted request leaves pending/unpaid and ledger delta 0, but the error occurs before the guard; that is not mismatch-defense proof.
10. Effects: first callback log exists with no mismatch processing reason. SQL failure prevents the USD variant. No real gateway calls/charges or product changes.

Repro: [WebhookMismatchTest](tests/WebhookMismatchTest.php); [B04 evidence](evidence/B04.json); [JUnit exception trace](../../.e2e/system-audit/benchmark/integration.xml). Current persistence: unknown. Blocker: [IdempotencyService:131](../../app/Modules/Shared/Application/Services/IdempotencyService.php#L131), `created_at + INTERVAL ttl_seconds SECOND > NOW()`, is rejected by SQLite before the amount check. The old [amount test](../../tests/Feature/PaymentsIntegrityRegressionTest.php#L117) mocks this service. No such mock was added to this pilot to force progress.

### B05 — BUG-VENDOR-BANK-DATA-001

Original: [issue report:952](../../docs/qa/instaparty-issues-2026-09-12.md#L952). Roles/modules: super-admin/vendor; Identity/Shared. Related **SC-Identity-e02e2e0de3** is adjacent profile updating; there is no exact admin default-bank-masking scenario link established.

1. Preconditions: populated synthetic bank data and an admin explicitly authorized to view/update the fixture profile. No reveal or save action is performed.
2. Database: fixture IBAN `EG000000000000000000000000001`, holder `BENCHMARK ONLY HOLDER`, bank `BENCHMARK ONLY BANK`; all are deliberately fake. Snapshot stored IBAN.
3. Actor: disposable super-admin with `view_vendor::profile` and `update_vendor::profile`; permission to edit does not authorize default plaintext display.
4. Browser: real admin sign-in; open the profile's generated edit URL; inspect visible bank input and DOM HTML before any disclosure action.
5. API: normal Filament page request; no replacement endpoint or direct model mutation after seeding.
6. Expected: masked default IBAN, no full raw value in HTML/Livewire snapshot, persisted IBAN unchanged. Existing security-mask convention supplies the explicit `EG00••••001` fixture oracle.
7. Incorrect: full IBAN appears by default or masking overwrites storage.
8. Assertions: input visible and nonempty; exact mask; raw marker absent from DOM HTML; input not equal to plaintext; database value unchanged.
9. Persistence: read-only SQLite comparison before/after page navigation; no save is clicked.
10. Effects: no bank mutation/reveal; authorized reveal, audit expiry, queue details, exports, encryption and other bank fields remain outside this branch.

Repro: [pilot test](../../tests/tester-army/benchmark/pilot.e2e.ts); [B05 evidence](evidence/B05.json); [final trace](../../.e2e/system-audit/benchmark/final-controls/results/pilot-b05-bug-vendor-bank-data-001-ed7d55def700a123/trace.md). Current persistence: default edit-IBAN exposure not reproduced. [EditVendorProfile:28](../../app/Modules/Identity/Filament/Resources/VendorProfileResource/Pages/EditVendorProfile.php#L28) applies masking before filling the form. No whole-record security closure follows.

## Executed commands and evidence ledger

| Run | Evidence | Interpretation |
| --- | --- | --- |
| Initial e2e `01a13178-926c-7bdb-8055-d85d337d9a58` | [JUnit](../../.e2e/system-audit/benchmark/e2e/junit.xml), [summary](../../.e2e/system-audit/benchmark/e2e/summary.md); 112.52s | Four cases selected: B01 locator ambiguity, B02 business assertion failed, B03/B05 passed their assertions. Initial B01 is not bug detection. |
| Focused `01a1317c-d401-7d25-a7d1-7a387e12126d` | [JUnit](../../.e2e/system-audit/benchmark/focused/junit.xml); 57.63s | Scoped favorites heading fixes locator ambiguity, without changing availability expectation. B01 now fails exact oracle; B03 repeats with item/idempotency persistence assertions. |
| Final controls `01a1317f-d541-7754-8494-a91fdb68c408` | [JUnit](../../.e2e/system-audit/benchmark/final-controls/junit.xml), [summary](../../.e2e/system-audit/benchmark/final-controls/summary.md); 80.48s | B01 confirms persisted `published` state before failing availability; B05 confirms actual input property mask and unchanged storage. |
| B04 integration | [JUnit](../../.e2e/system-audit/benchmark/integration.xml); 31.63s | One known-defect case attempted, blocked before target guard by real idempotency SQL. Not a sixth defect case. |

```bash
# From the project root in Ubuntu WSL:
npx e2e run --config e2e.benchmark.config.ts --output .e2e/system-audit/benchmark/e2e
npx e2e run --config e2e.benchmark.config.ts --grep 'B01|B03' --output .e2e/system-audit/benchmark/focused
npx e2e run --config e2e.benchmark.config.ts --grep 'B01|B05' --output .e2e/system-audit/benchmark/final-controls
```

The PHP command was run with the installed Windows PHP runtime against the asserted memory database:

```text
php vendor/bin/pest qa-audit/benchmark/tests/WebhookMismatchTest.php --log-junit .e2e/system-audit/benchmark/integration.xml
```

Expected current exits are nonzero for the benchmark due to confirmed repro failures and the recorded environment blocker. None is wired into passing CI. The old normal suite's 78 passes and PHP suite's 563 passes were not rerun or reused as proof of these five intended outcomes.

Delivery checks: `npx e2e list --config e2e.config.ts --reporter json` selected 77 desktop pairs and zero benchmark tests, without starting an app or executing another case. PHP syntax checks passed for both fixture seed files and `WebhookMismatchTest.php`. The evidence reconciliation self-check verified the exact persisted/API outcomes, five CSV rows and their classifications. Local links in the two main Markdown reports resolve. These checks do not add runtime coverage beyond the five pilot cases.

Reports: [DETECTION_RESULTS.csv](DETECTION_RESULTS.csv), [MISSED_DEFECT_ROOT_CAUSES.md](MISSED_DEFECT_ROOT_CAUSES.md), [sanitized evidence](evidence/). The two confirmed bugs remain unfixed. No expansion beyond five cases was performed.
