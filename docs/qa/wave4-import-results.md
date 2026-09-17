# Wave 4 — Catalog Excel imports

**Issue:** BUG-IMPORT-001  
**Date:** 2026-09-13  
**Scope:** Catalog Excel import actions, import status/detail rendering, and focused Feature coverage.

## Root cause

The rental, sale, and digital import actions created an `excel_imports` row as
`pending`, then ran parsing, validation, and service creation synchronously.
An exception from the parser or service action escaped without a terminal
status update, leaving a row with no counters or error record. There are no
Catalog import Job classes or queue dispatches in this checkout, so the
observed stale row was not waiting on a local Job class.

The detail page also read `row_data[$field]` and bilingual message entries
without handling older runtime failure rows where `row_data` or `message` can
be null or incomplete. That made a failure record capable of producing the
reported HTTP 500.

## Remediation

- Added `FailExcelImportAction`, which atomically changes non-terminal imports
  to `failed`, preserves the greatest known row count, resets imported rows,
  records one generic bilingual import error, and avoids duplicate failure
  rows. Exception messages, paths, and spreadsheet contents are not persisted.
- All three import actions now mark the row `processing` before parser work,
  treat parser and service-creation `Throwable` failures as sanitized terminal
  failures, and keep service writes inside the existing transaction so a
  failed import cannot commit a partial batch.
- Empty parsed files now end as `failed` with an import-level error instead of
  `completed` with zero rows.
- The admin resource localizes `processing` and status values. The detail page
  safely renders missing or malformed row data/messages and still supports all
  rental, sale, and digital product types.

## Evidence

Focused checks were run outside the isolated runner because the Laravel test
bootstrap needs the real project path:

| Check | Result |
|---|---|
| `php vendor/bin/pest tests/Feature/CatalogExcelImportTest.php --compact` | PASS — 6 tests, 57 assertions |
| `php vendor/bin/pest tests/Feature/CommunicationSecurityAndDetailTest.php --filter="spreadsheet import detail" --compact` | PASS — 1 test, 2 assertions |
| `php vendor/bin/pint app/Modules/Catalog/Application/Actions/FailExcelImportAction.php app/Modules/Catalog/Application/Actions/ImportRentalServicesFromExcelAction.php app/Modules/Catalog/Application/Actions/ImportSaleServicesFromExcelAction.php app/Modules/Catalog/Application/Actions/ImportDigitalServicesFromExcelAction.php tests/Feature/CatalogExcelImportTest.php` | PASS |
| `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M` on the four import actions | PASS — no errors |
| PHP syntax checks on changed PHP files | PASS |
| Earlier full `php vendor/bin/pest --bail --compact` | PARTIAL — reached 54 passed, then stopped at unrelated `tests/Feature/InventoryExpiryTest.php:51` (`$expired` undefined); 63 pending, 855 assertions at stop |

The focused regression tests cover parser failure for rental/sale/digital,
service-creation failure with rollback, valid rental import, invalid row
errors with bilingual data, empty files, and malformed admin detail records
for all product types.

## Closure state

**BUG-IMPORT-001: PARTIAL.** The code path that caused new pending rows and the
detail rendering failure is covered and fixed in the local checkout. The
reported historical production import was not retried, changed, or deleted.
Browser acceptance remains open because the shared verification fixture
currently creates zero Identity country records after violating the unique
`countries.iso3` constraint. This slice does not alter shared Browser fixtures
or production data.

## Browser fixture handoff

Use a deterministic Identity fixture that creates one country with a unique
ISO3 (for example `EGY`) and reuses that row for dependent governorate/city
records and the vendor. Do not create the same ISO3 inside each loop and do
not disable the constraint. Seed the admin identity, authorized import, vendor,
and import error records through factories, then navigate using the created
import `public_id` rather than the historical production ID. Once that fixture
is corrected, verify the admin detail route in the required role and locale.
