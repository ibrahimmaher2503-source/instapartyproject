# Independently reproduced findings

Three newly confirmed runtime/infrastructure findings are recorded below. RQA-001 and RQA-002 are failures of two selected original catalog scenarios. RQA-003 is a schema-environment control, not a twenty-first catalog scenario. None was fixed. Existing TA-SYS-001/002 remain historical controls and are not reclassified as new discoveries.

New means a newly demonstrated failure with this trigger/root cause in the reviewed source register, not a claim that no earlier analyst ever mentioned a related risk. Static discovery already identified broad retry/concurrency risks; this campaign supplies committed runtime proof.

## RQA-002 — P1: competing sale additions oversell finite stock

- Scenario: **SC-Booking-320197067b**, original P1. Roles/modules: two different customers, Booking/Catalog, approved service owner. Engineering ownership/transaction rules and the original scenario's finite-stock contract supply the oracle: stock must not be exceeded and the loser receives 409.
- Preconditions: MariaDB 11.8.6, Laravel `mysql` driver, `REPEATABLE-READ`; published sale service at 10,000 EGP minor units with `stock_quantity=1`; two distinct owned draft bookings; no preexisting reservations. The real CatalogServiceReader is asserted to return stock 1.
- Actions: start two independent PHP processes, each invoking the real AddItemToBookingAction for quantity 1. Both bootstrap before a parent process holds the actual service row lock and releases a shared start barrier; after 250ms of contention the parent commits. Wait for both workers and inspect committed inventory.
- Expected: exactly one accepted operation and one 409, one item and one held unit; no second booking stock allocation.
- Observed: both workers return 201 and two nonexpired held units persist for the same service, despite stock 1. The independent repro fails `capacity preserved in committed reservations`: **expected 1, actual 2**. The retained evidence also records `[201,201]` worker statuses, stock row and both held reservations.
- Impact: customer commitments exceed available sale inventory. No real order fulfillment, money movement or production stock was involved.
- Root-cause mechanism supported by source: [AddItemToBookingAction](../../app/Modules/Booking/Application/Actions/AddItemToBookingAction.php) begins a transaction and reads booking/service before locking the service. Its later reservation SUM is a nonlocking consistent read. Under REPEATABLE READ a pre-wait snapshot can omit the other transaction's newly committed hold. The lock exists, but does not make that SUM a fresh locking read. This mechanism is inferred from the current source, observed isolation and the forced-contention repro; a READ COMMITTED counterfactual was not executed.
- Standalone failing repro: [NewDefectReproTest.php](tests/NewDefectReproTest.php), `test_independent_repro_RQA_002_competing_sale_holds_must_not_exceed_stock`; [real worker](tests/inventory-worker.php). Shared implementation: [FinancialPilotTest.php](tests/FinancialPilotTest.php), `test_SC_Booking_320197067b_two_processes_cannot_oversell_one_unit`.
- Evidence: [committed stock/holds and exact assertion](evidence/SC-Booking-320197067b.json), [independent JUnit](../../.e2e/risk-program/mysql/run-1791835365-373/junit.xml), [independent run log](../../.e2e/risk-program/mysql/run-1791835365-373/run.log). Earlier independent run [also failed](../../.e2e/risk-program/mysql/run-1791835006-361/junit.xml) on a different disposable database.
- Limits: actual concurrency is proven for two Action callers on this engine/isolation. HTTP authorization is a separate passing scenario. All service types, other isolation levels, queue workers and production behavior remain unverified.

## RQA-001 — P1: webhook idempotency scope is silently coerced and cannot be read back

- Scenario: **SC-Payments-db566d3009**, original P0. Roles/modules: gateway/system; Payments/Shared/Booking/Settlement. Original intended outcome: repeated callback is recognized, duplicate log recorded, processing skipped. The repository requires idempotency; the expectation was not changed to accept a merely present row.
- Preconditions: fresh socket-only MariaDB fixture, confirmed unpaid booking, pending 10,000 EGP payment; external gateway signature/parser fixture produces matching success. Routing, idempotency, capture, ledger and after-commit listeners are real.
- Actions: POST `/api/v1/webhooks/paymob` twice with identical transaction/order/type/success fields. Query the real IdempotencyService with scope `internal_webhook` and the deterministic `paymob:PURCHASE:{bookingId}:{gatewayRef}:true` key. Inspect persisted scope/TTL and callback logs.
- Expected: the key is retrievable within its real scope, the second log is `duplicate_idempotent_replay`, and no repeated financial/event effects occur.
- Observed: the row has **`scope=""`**, while the service queries `internal_webhook`; `hasBeenUsed` returns false. Both logs have null processing_error. The independent intended-outcome assertion fails **expected true, actual false**. Application and database UTC timestamps are aligned; the row TTL is unexpired, so clock/expiry is not the cause.
- Impact: application replay recognition/logging is ineffective for this scope. **No duplicated capture money or events were observed**: the separate CapturePaymentAction captured-state guard preserved the existing two ledger entries and event counts. Other callback types and refund side effects were not tested; do not infer repeated charges.
- Root cause: [scope migration](../../app/Modules/Payments/Database/Migrations/2026_05_15_000016_alter_idempotency_keys_add_scope_ttl_payload.php) permits only `http`/`internal`. [IdempotencyService](../../app/Modules/Shared/Application/Services/IdempotencyService.php) writes/queries `internal_webhook` and uses `insertOrIgnore`; the database coerces the unsupported enum to its empty value on the ignored insert. A successful insert count is insufficient evidence of a valid stored scope.
- Standalone failing repro: [NewDefectReproTest.php](tests/NewDefectReproTest.php), `test_independent_repro_RQA_001_webhook_scope_must_be_retrievable`; shared exact requests/assertions in [FinancialPilotTest.php](tests/FinancialPilotTest.php), `test_SC_Payments_db566d3009_duplicate_callback_has_no_financial_or_event_effect`.
- Evidence: [scope, timestamps, logs and unchanged finances](evidence/SC-Payments-db566d3009.json), [independent JUnit](../../.e2e/risk-program/mysql/run-1791835365-373/junit.xml), [prior independent JUnit](../../.e2e/risk-program/mysql/run-1791835006-361/junit.xml).
- Limits: external signature verification/parsing is substituted; this does not test real Paymob cryptography. Live application execution was on MariaDB. Oracle MySQL cannot reach this flow from a clean schema because RQA-003 blocks migration.

## RQA-003 — P1 infrastructure: current migrations cannot bootstrap Oracle MySQL 8.4

- Catalog relationship: no exact original scenario ID established; prerequisite to the selected financial integration cases. Recorded as a distinct infrastructure finding, without manufacturing an ID or adding pilot coverage.
- Preconditions: private Oracle MySQL **8.4.11** instance, empty uniquely named `instaparty_qa_*` database, Unix socket only, current unmodified migrations, generated test-only environment. No production data or credentials.
- Actions: execute real `migrate:fresh --force --no-interaction` on that guarded empty database.
- Expected: migrations create the test schema without syntax error (exit 0), allowing the user-requested MySQL test infrastructure to reach application behavior.
- Observed: migration `2026_05_21_000002_fix_ledger_transaction_groups_balanced_check.php` fails with SQLSTATE 42000 / 1064 at `ALTER TABLE ledger_transaction_groups DROP CONSTRAINT IF EXISTS ltg_balanced_check`. Standalone repro fails **expected exit 0, actual 1**. The original 13-case MySQL attempt had zero business assertions and is BLOCKED evidence, not thirteen product bugs.
- Source: [migration](../../app/Modules/Settlement/Database/Migrations/2026_05_21_000002_fix_ledger_transaction_groups_balanced_check.php). MariaDB supports that clause ([official ALTER TABLE reference](https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table)); succeeding there does not establish Oracle MySQL compatibility.
- Standalone failing repro: [SchemaCompatibilityReproTest.php](tests/SchemaCompatibilityReproTest.php), `test_independent_repro_RQA_003_current_migrations_must_bootstrap_empty_MySQL`. This uses the framework base TestCase so empty-schema reproduction does not depend on the normal TestCase's preexisting roles.
- Evidence: [exact engine/error/command outcome](evidence/RQA-003.json), [independent assertion JUnit](../../.e2e/risk-program/mysql/run-1791835287-379/junit.xml), [original blocked attempt](../../.e2e/risk-program/mysql/run-1791833538-597/junit.xml).
- Limits: production engine/version is unknown and was not inspected; this proves failure on the stated empty MySQL 8.4 environment. No migration was skipped, marked as complete or edited to enable a pass.

## Reproduction commands and CI separation

From Ubuntu WSL at the checkout root, after the recorded runtime prerequisites:

```bash
# Two independent intended-outcome failures; default CI never collects this directory.
QA_TEST_FILE=qa-audit/risk-program/tests/NewDefectReproTest.php \
QA_TEST_FILTER=independent_repro bash qa-audit/risk-program/run-mysql.sh mariadb

# Empty MySQL infrastructure failure, separate from the twenty scenario results.
QA_TEST_FILE=qa-audit/risk-program/tests/SchemaCompatibilityReproTest.php \
QA_TEST_FILTER=independent_repro bash qa-audit/risk-program/run-mysql.sh mysql
```

The independent repro class reuses the reviewed real-service fixture/assertions. The explicit filter selects only the two repro methods; otherwise PHP would also collect inherited pilot methods. Each run creates a new private database and committed fixtures. Failing assertions stay failing; neither expected results nor product code were adjusted to produce green output.

## Exploratory findings that are not confirmed new defects

- Stale-context charter completed three steps but did not establish cart/date divergence. Its generic range-error wording warning is insufficient for a defect: the provided end-before-start input is invalid and was rejected, and no approved field-specific message contract was recovered. [Trace](../../.e2e/risk-program/explore-stale-context/results/explore-sign-in-as-credential-customer-e0b8a36185dedd14/trace.md) preserves the observations; model claims do not count as independent reproduction.
- Profile-validation charter was blocked by the ChatGPT provider usage limit before any charter step. No profile defect or coverage is awarded.
- Late failed callback precedence and legacy partial-refund state assignment remain **NEEDS_BUSINESS_DECISION** for business acceptance. Discovery describes unsafe-looking current behavior, but that is not approval to encode it as the intended correct outcome. They were not added to this twenty-scenario pilot.
