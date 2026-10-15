# Report-driven tester.army system execution

Scope: local isolated InstaParty checkout, tester.army e2e 0.19.0 with ChatGPT gpt-6-luna. Existing discovery artifacts remain the reference, not evidence of runtime success. No production, commits or pushes are included.

Reference baseline: `docs/qa-discovery/catalog_data.json` and its Excel workbook contain 1,292 scenarios, 520 flows, 17 application modules plus Firebase. The 145 gaps and 31 proposed scenarios remain questions unless code and an approved expectation establish the result. Historical QA outcomes are not carried forward as current passes.

## Execution order

1. Seed isolated customer/other-customer, approved vendor, admin and rental/sale/digital services. Run public navigation, localization, login, customer account, panel entry, public API and authorization checks. Page checks prove rendering/access only.
2. Exercise customer mutations, wishlist, addresses and planner/cart lifecycle with ownership/validation checks. Record exact scenario IDs only for behavior actually asserted.
3. Exercise vendor onboarding/service/booking contribution and admin moderation/support surfaces. Verify candidate bugs using deterministic repro tests before calling them confirmed.
4. Exercise local payment/refund/settlement/withdrawal contracts with explicit fake gateway boundaries. Never interpret fake capture as real gateway acceptance.
5. Cover communication, reviews, loyalty, promotions, shared/CMS, subscriptions and trust/safety through their actual UI/API surfaces. Internal actions and concurrency need separate execution evidence.
6. Reconcile every discovery scenario in a machine-readable execution matrix: passed, failed, blocked, needs-business-confirmation or not-run. Link tests, reports, evidence and blockers; do not infer all branches from a successful primary flow.

## Rules and environment limits

- New random test credentials are generated per isolated database and stored only in ignored `.e2e/credentials.json`; tests use e2e secret handling. No user credentials or production data.
- Run one suite at a time: fixture/credential manifests currently belong to the active run. Independent explorers may share a separately started app but must not start concurrent seeders.
- Use the installed e2e skill for tests and exploration. Models produce candidate findings; exact assertions establish repros.
- Firebase emulation, real SMS/email, Paymob callbacks and financial concurrency require explicit environment evidence. Missing external services are blockers, not passes or confirmed product defects.

## Current execution

The broad local execution wave is complete for the normal desktop suite: **77 tests / 7 files passed**, exit 0, run `01a13162-af34-7c21-9f51-d18d105fa747`, 541.66 seconds including 35.63 seconds startup. This is tester.army e2e, not the project's pre-existing Playwright test command. Six agent steps used three verified replays and three live ChatGPT model calls; reported usage was 13.4k tokens, 32% provider cached.

| Evidence | Result | Boundary |
| --- | --- | --- |
| Public EN/AR pages and three service types; account navigation; API contracts | Normal suite passed | Rendering, identity and exact asserted response behavior only |
| Admin/vendor resource indexes | 84 admin + 3 vendor tables rendered; the remaining registered admin settlement-run index correctly denied access | Does not prove create/edit/action correctness |
| Wishlist/draft/sale chain | Mutations, ownership, checkout EGP 125, submission, vendor acceptance, repeated fake payment initiation passed | Booking remains unpaid; no real charge or callback |
| Planner and price filtering | Four bilingual tests passed, including past/reversed dates and city hierarchy | No complete rental/digital fulfillment |
| Dedicated mobile check | One test passed at an asserted 390px viewport, RTL menu close → search → English switch; no horizontal overflow on the checked search pages | No exhaustive mobile layout/device audit |
| Existing PHP suite | 563 passed, 5,061 assertions, 619.33 seconds | Separate internal proof; disposable SQLite, not browser proof |
| Firebase functions | Nine unit tests + seven Firestore rules emulator tests passed; TypeScript build passed | No deployed function-to-Laravel mirror or real chat delivery |
| Dedicated bug repro suite | Two assertion failures confirming two defects | Kept outside the normal passing suite; app defects are not fixed |

Desktop reports: `.e2e/system-audit/final/summary.md`, `junit.xml`, and 77 traces under `results/`. Supporting commands and counts are in [supporting checks](tester-army-system/supporting-checks.json).

Mobile run `01a1316b-744b-73c2-88b7-f978e755b9ea` passed with exit 0 in 50.60 seconds, including 36.74 seconds startup. Reports: `.e2e/system-audit/mobile/summary.md`, `junit.xml`, and its trace. Total normal tester.army checks: **78 passed**, desktop and mobile combined.

The five initial explorations consumed about 1.10 million reported tokens / 123 model calls. Four covered desktop discovery/vendor/auth/planner; the intended mobile charter actually selected desktop because of the nested config/target behavior. Its results do not count as mobile acceptance. Dedicated root configs correct path resolution and explicit target selection. Explorers reported three issues and two warnings; none of those issues is confirmed: identical prices were deliberate fixture data, price filtering contradicted the finding title, and modal-menu interception is expected. The planner's missing city was a fixture hierarchy defect, now corrected and retested.

Confirmed independent failures are [TA-SYS-001 and TA-SYS-002](tester-army-system/confirmed-findings.md): published wishlist availability is false, and accepted chat report input reaches an unhandled server exception. Each has a failing deterministic e2e repro. No application fix, commit, push or production change was performed in this testing wave.

## Scenario reconciliation and remaining gates

[Execution matrix](tester-army-system/scenario-execution.csv) lists all 1,292 discovery scenarios. Exact-ID linkage currently establishes **3 partially-tested scenarios, 31 needing business confirmation, and 1,258 without exact linked execution evidence**. The 77 test count and 563 internal tests must not be converted into a claim that all 1,292 scenarios passed. See [coverage boundaries](tester-army-system/coverage-boundaries.md) for the behavior covered and the remaining workflows.

Full scenario reconciliation, resource mutations, complete rental/digital fulfillment, populated financial and account behavior, concurrency, real Paymob callbacks, email/SMS and deployed Firebase integration remain open. Local unit/emulator success does not establish production readiness.

The functions dependency install uses the existing manifest and adds a lockfile. Its latest dependency audit reports 28 findings (17 moderate, 9 high, 2 critical); no upgrade or audit fix was applied. Functions tests/build ran on Node 24.15.0 while deployment declares Node 20. Firestore rules used the existing Android Studio Java 21 runtime after the default Java 11 was rejected. Generated `functions/lib` and runtime logs are ignored.

## Reproduce the audit

Run from the project root inside Ubuntu WSL, with the previously completed ChatGPT sign-in:

```bash
npm run test:e2e -- --output .e2e/system-audit/final
npx e2e run --config e2e.mobile.config.ts --output .e2e/system-audit/mobile
npx e2e run --config e2e.repro.config.ts --output .e2e/system-audit/repros
python3 tests/tester-army/reconcile.py .e2e/system-audit/final/junit.xml .e2e/system-audit/mobile/junit.xml .e2e/system-audit/repros/junit.xml .e2e/system-audit/phpunit.xml
```

The repro command is expected to exit 1 until the two confirmed bugs are fixed. Run seed-starting suites sequentially. Explorations can be repeated with `bash scripts/tester-army-system-charters.sh`; the script starts one isolated server, runs four desktop charters concurrently, then a genuinely separate mobile target, and stops its server.
