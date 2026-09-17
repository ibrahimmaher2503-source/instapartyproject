# Wave 5 — withdrawal audit detail

Date: 2026-09-13  
Scope: `BUG-WITHDRAWAL-001`, local isolated test data only.

## Finding

The reported source issue says that opening a rejected withdrawal audit record
returned HTTP 500. The withdrawal request and settlement actions already use
minor-unit integers, database transactions, row locking, idempotency keys and
append-only ledger entries. No withdrawal transition, stale request, ledger
row or production record was changed in this wave.

The audit detail had a concrete presentation failure: the English and Arabic
payment-note entries declared a `string` return type but returned any value
stored under the locale key. A malformed nested array therefore raised a
`TypeError` while rendering the Filament page and returned HTTP 500.

## Fix

`WithdrawalResource` now renders a localized payment note only when the stored
value is a non-empty string. Any malformed or missing locale value is shown as
the existing em dash placeholder. This is a presentation guard and does not
change withdrawal state, amount, currency, bank data, ledger history or
authorization.

The `Payment` model now documents its existing `amount_minor` BIGINT and
`amount_currency` CHAR(3) columns. This removes the two corresponding PHPStan
property findings without changing runtime behavior.

## Evidence

Focused command, run outside the sandbox because the sandbox cannot resolve
the application root for Pest:

```text
php vendor/bin/pest tests/Feature/WithdrawalAuditDetailTest.php --bail
PASS — 2 tests, 6 assertions, 7.19 seconds
```

The regression covers a normal rejected audit record without ledger links and
a rejected record containing malformed nested English/Arabic payment notes.
Before the fix, the latter reproduced HTTP 500 with:

```text
TypeError: WithdrawalResource::{closure}(): Return value must be of type string, array returned
```

After the fix, both records return HTTP 200 and the malformed nested value is
not rendered.

Targeted Pint passed for the changed model and regression test. Pint's check
on the existing `WithdrawalResource` also reports historical line-ending and
import-order findings; no broad reformat was applied. Targeted PHPStan no longer
reports `Payment::$amount_minor` or `Payment::$amount_currency`. The remaining
findings are pre-existing generic relation/scope typing and action return or
argument issues; no ignores or baseline entries were added.

## Remaining status

`BUG-WITHDRAWAL-001` remains **OPEN/PARTIAL**. The audit-detail 500 path is
guarded and covered locally, but stale pending withdrawals, bank validation,
dual-control approval, payout proof, concurrent requests and end-to-end
multi-party operational evidence were not exercised. No closure is claimed
for those contracts.

## Sequential integration

After the activity and data owners' changes were stable, the shared suites were
run sequentially against isolated local data:

```text
php vendor/bin/pest --bail
PASS — 139 tests, 1,289 assertions, 106.82 seconds

npm run e2e -- --timeout=120000 --workers=1
PASS — 16 tests, 3.7 minutes
```

The first unmodified Playwright invocation stopped in setup after 34.4 seconds
because its default 30-second test timeout was exceeded; the application tests
did not run in that attempt. The rerun above completed the same full suite with
the timeout explicitly raised. The limited withdrawal Browser test and setup
also passed 2/2 in 41.8 seconds before the full run.

Pint passed for `Payment.php` and the focused withdrawal test. The Resource
file's `--test` check still reports two pre-existing formatting fixers (line
ending and import order); the unused import and fully-qualified name findings
were corrected locally without changing behavior. No broad reformat was
applied. PHPStan no longer reports the two real
`Payment::$amount_minor` and `$amount_currency` findings. Its focused run still
reports 13 existing Payment model generic/scope typing findings; no ignores or
baseline entries were added.
