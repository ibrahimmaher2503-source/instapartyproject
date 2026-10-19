# Blockers, environment limits and unresolved decisions

## B04 status: advanced on MariaDB, still blocked on Oracle MySQL

The former SQLite blocker was real MySQL interval SQL in IdempotencyService before the mismatch guard. The new socket-only MariaDB environment executes that SQL without mocking it. All three mismatches (9,999 EGP, 10,000 USD, absent captured amount) reach `captured_amount_or_currency_mismatch`, retain pending/unpaid state, produce no ledger delta and no PaymentCaptured event. [Evidence](evidence/SC-Payments-3436fcba08.json).

Oracle MySQL 8.4.11 cannot create the current schema because of RQA-003. [Independent migration failure](evidence/RQA-003.json). The mismatch success on MariaDB does not remove the MySQL blocker or certify production. Neither the idempotency service nor capture/ledger code was mocked or patched.

## Exploration provider limit — BLOCKED

The second focused tester.army charter failed with `MODEL_PROVIDER_FAILED: The usage limit has been reached`, before any charter step. One model call was attempted, zero tokens returned; duration 114.35s including startup 42.14s. [Runner summary](../../.e2e/risk-program/explore-profile-validation/summary.md), [trace](../../.e2e/risk-program/explore-profile-validation/results/explore-sign-in-as-credential-customer-9478caff1b0d0ec3/trace.md).

No automatic model/provider switch, repeated sign-in or extra quota-consuming retry was attempted. The selected deterministic twenty-scenario pilot and existing defect controls completed independently of model calls. The profile exploratory area remains untested; absence of findings is not a pass.

## Authority and incomplete evidence

- Authoritative PRD/constitution/feature specs remain missing in this checkout. Code-derived catalog outcomes do not prove product approval. Original expected results remain intact; approved repository invariants support bounded money/security assertions.
- FR-2 historically requires event end; WIZ-01 records optional end. The original conflict is retained pending authority/date reconciliation. No invented mandatory/optional policy was added.
- Terminal captured-payment precedence for a later failed callback, legacy partial-refund status and wider subscription/tax/dispute scope need business decisions. Static observations are not asserted as correct product outcomes.
- Six selected definitions remain PARTIALLY_TESTED despite passing transport/branch assertions: their unobserved event/type/multi-vendor effects or invalid idempotency scope prevent full acceptance. [Per-scenario limits](PILOT_EXECUTION_RESULTS.md).
- Source omissions include 690 missing preconditions, 368 missing affected-entity descriptions and all 1,292 blank related-scenario lists. Matrix/layer mappings are design records; missing exact fixture/selectors remain gates for unselected cases.

## Deliberate test-environment limits

- Real Paymob HMAC/transport, real bank transfers, Firebase/email/SMS providers and production credentials were not used. Financial gateway signature/parser/refund boundary is simulated, with real downstream services and no-network HTTP guard. Real provider acceptance remains unverified.
- File-cache locks are exercised through the actual WalletLocker implementation; production Redis availability/locking semantics are not tested. The stock-concurrency case uses real database row contention in independent processes.
- Browser app uses the guarded local SQLite runner; financial tests use MariaDB via the `mysql` driver. This separation is explicit, not parity proof. Only English desktop browser readback was deterministically verified in the selected lifecycle.
- No production schema/data inspection, deployment, application defect fix, commit or push was performed. The default MySQL service auto-started by the runtime install was stopped and disabled; private test daemons are shut down by runner traps. Final process inspection found no running mysqld/mariadbd.
- An early QA source archive included the checkout's existing `database/database.sqlite` unintentionally. It was never queried or used as the test connection. The unused copied member and temporary copies were removed; the final runner copies only migrations/factories/seeders. The original database was not edited. No original `.env` was copied.

## Harness errors distinguished from product failures

Initial MySQL collection hit a PHPUnit reserved helper name, corrected in test-only code. The first browser snapshot diagnostic assumed a `type_snapshot.service_public_id` field the add-item contract does not define; current source and existing commercial-snapshot tests establish `service_id` plus bilingual `name_snapshot`. The corrected assertion checks those exact fields, preserving the original commercial snapshot outcome. It does not accept a missing required field.

DatabaseMigrations initially reversed populated ledger migrations at teardown and failed on old unique/enum constraints. DatabaseTruncation uses actual fresh migration then truncates only the disposable database, retaining real commit/after-commit behavior. A generated empty test environment file removed harmless missing-file warnings; no production configuration was imported.

The first schema repro inherited normal role creation before schema existed; using Laravel's base TestCase allowed the isolated migration itself to reach its unchanged exit-0 assertion. Those harness failures are retained in run artifacts and never promoted into business detection or passes.

**Stopped at twenty distinct selected catalog scenarios.** Remaining P0 cases, wider browser coverage and the blocked exploratory charter require a later explicitly scoped phase.
