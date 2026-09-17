# Wave 5 — Unicode vendor data

**Issue:** BUG-DATA-001  
**Date:** 2026-09-13  
**Scope:** Vendor `business_name` storage, update/API round-trip, and user-facing
Identity, Catalog, and Booking displays.

## Contract found before the change

`vendor_profiles.business_name` is a JSON column with mandatory `en` and `ar`
translations. `VendorProfile` uses `spatie/laravel-translatable`, and its
registration and update actions pass an EN/AR array through the model. The
vendor-profile migration and MySQL/MariaDB connections already declare
`utf8mb4` and `utf8mb4_unicode_ci`. Public API resources use `public_id` ULIDs.

This contract means a display layer can select a valid locale and fallback,
but it cannot reconstruct an Arabic value whose stored bytes were already
replaced with literal question marks. No authoritative replacement source was
present in this checkout.

## Root cause and remediation

The confirmed failure mode is data loss before or during persistence: a legacy
Arabic value can be stored as literal `?` characters. Changing HTML or locale
selection cannot restore those bytes. New writes already use the translatable
JSON path and the database connection is configured for UTF-8.

The existing `StorefrontText` service now provides the display fallback for
unavailable translations. Identity, Catalog, and Booking user-facing vendor
name displays use that service, so a corrupted Arabic value falls back to the
authoritative English value without changing the database row. Valid Arabic
continues to render in Arabic. No migration, guessed name, production query,
or destructive backfill was added.

## Changed files

- `app/Modules/Identity/Filament/Resources/VendorApprovalQueueResource.php`
- `app/Modules/Identity/Filament/Resources/VendorApprovedProductTypeResource.php`
- `app/Modules/Identity/Filament/Resources/VendorBusinessHourResource.php`
- `app/Modules/Identity/Filament/Resources/VendorCoverageAreaResource.php`
- `app/Modules/Identity/Filament/Resources/VendorDocumentFilamentResource.php`
- `app/Modules/Identity/Filament/Resources/VendorProfileResource.php`
- `app/Modules/Catalog/Filament/Resources/RentalServiceResource.php`
- `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php`
- `app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php`
- `app/Modules/Catalog/Filament/Resources/ExcelImportResource.php`
- `app/Modules/Catalog/Filament/Pages/PendingServiceEditsPage.php`
- `app/Modules/Booking/Filament/Resources/BookingModificationResource.php`
- `app/Modules/Booking/Filament/Resources/BookingResource/Pages/CreateBooking.php`
- `app/Modules/Booking/Filament/Resources/AdminBookingInterventionResource/Pages/ViewBookingIntervention.php`
- `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingVendorsRelationManager.php`
- `app/Modules/Booking/Http/Resources/BookingVendorResource.php`
- `app/Modules/Identity/Http/Resources/VendorProfileResource.php`
- `tests/Feature/VendorUnicodeDataTest.php`

`StorefrontText` was reused; its contract and implementation were not changed.

## Verification

Checks ran outside the isolated runner because the Laravel bootstrap needs the
real project path:

| Check | Result |
|---|---|
| `php vendor/bin/pest tests/Feature/VendorUnicodeDataTest.php --compact` | PASS before the final API fallback/admin assertion additions — 2 tests, 10 assertions; rerun pending |
| `php vendor/bin/pest tests/Feature/CatalogExcelImportTest.php tests/Feature/CommunicationSecurityAndDetailTest.php tests/Feature/FilamentVendorProfileDetailTest.php tests/Feature/VendorAdminSearchTest.php --filter='import|spreadsheet|vendor profile|approval checklist|searches vendor administration' --compact` | PASS — 11 tests, 83 assertions (before the final API fallback/admin assertion additions) |
| Pint on changed PHP files | PASS before the final API/test additions; `--test` rerun was blocked by the isolated runner path |
| PHP syntax checks on all changed PHP files | PASS, including the final API/test additions |
| Final focused rerun | BLOCKED by the current usage-limit review for the required escalated Laravel runner; unprivileged runner cannot bootstrap facades |
| Existing full-suite baseline | UNVERIFIED for this wave; previous run stopped at unrelated `InventoryExpiryTest.php:51` (`$expired` undefined) |
| Browser acceptance | UNVERIFIED; shared Identity fixture still has the `countries.iso3` uniqueness blocker |

The focused coverage proves valid Arabic persistence/update and API resource
output, legacy question-mark fallback, and existing admin display/search
journeys. It does not prove recovery of production rows or every Browser route.

## Affected-record and repair plan

No production or shared runtime database was queried. The safe inventory query,
to be run only against an approved read-only snapshot, should identify vendor
rows whose `business_name` locale value consists only of replacement/question
mark characters and return only `public_id`, locale, and a masked preview.
Each repair must require an authoritative vendor source, record the source and
actor, update only the affected locale, and be rerunnable by stable `public_id`.
Rows without authoritative evidence remain unchanged. This wave intentionally
does not add or run that backfill.

## BUG-FINANCE-IDENTITY-001

**UNVERIFIED / out of scope.** The issue concerns internal numeric IDs and PHP
morph-class names in settlement/refund/wallet/reconciliation screens. The
Unicode contract is `VendorProfile.business_name`; it does not define the
financial entity presenter or ledger route contracts. The finance slice was
not modified, and Activity, Withdrawal, ledger, and shared e2e files remain
outside this wave.

## Browser fixture handoff

Use one deterministic country row with a unique ISO3 such as `EGY`, reuse it
for dependent geography records, and avoid creating the same ISO3 inside test
loops. Seed a vendor with valid Arabic and a separate legacy fixture with
literal question marks, then navigate using its generated `public_id`. Keep
database constraints enabled and verify the admin/API journeys in both EN and
AR once the shared fixture is repaired.
