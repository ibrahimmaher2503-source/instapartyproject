# Wave 6 — UX-SERVICE-001

Date: 2026-09-13

## Scope and proven trigger

This slice covers the Catalog service forms and the three admin service import
pages named in `docs/qa/instaparty-issues-2026-09-12.md`. The reported defects
were raw English page titles in Arabic admin import screens and raw English
service-form vocabulary/status fallbacks.

## Changes

- Added locale-aware `getTitle()` methods to the rental, sale, and digital admin
  import pages. Existing vendor import page titles were already localized.
- Replaced raw translation-tab names and language labels in the admin and vendor
  rental, sale, and digital service resources with Catalog translations.
- Localized sale customization field labels and option labels in both locales.
- Added the missing gallery label to vendor service uploads.
- Kept service names as the primary table identity and added a copyable public
  ULID column that is hidden by default on admin and vendor tables.
- Made service status table formatting resolve known raw values through
  `ServiceStatus`, with a neutral fallback for unknown values so enum values do
  not appear as UI text.
- Localized the vendor aggregate service-list edit action and applied the same
  safe status fallback there.
- Removed dead raw English admin navigation-label properties; the existing
  localized navigation methods remain the source of display text.

No business rules, persistence/schema, packages, `.env`, subscription,
finance-identity, shared e2e, or ledger files were changed.

## Evidence

| Check | Result |
|---|---|
| PHP syntax on 11 changed PHP files | PASS — no syntax errors |
| `php vendor/bin/pest tests/Feature/ServiceUiLocalizationTest.php --bail` | PASS — 2 tests, 30 assertions |
| Targeted Pint on changed service pages/resources/translations test | PASS |
| Targeted PHPStan | BLOCKED_BY_CONFIG — `phpstan.neon` excludes `app/Modules/*/Filament/**/*`; direct analysis returned `No files found to analyse` |
| Real Browser acceptance | UNVERIFIED — requires the coordinator's headed Browser session and seeded records |

## Browser fixture for the coordinator

Seed one authorized admin and one authorized vendor, with Arabic and English
locales available. Seed one service per product type with translated names and
public ULIDs, and include records in `draft`, `pending_review`, `changes_requested`,
`published`, `rejected`, and `archived` states where the existing factories and
state transitions permit them. Do not expose or seed numeric IDs as user-facing
identifiers.

Verify in Arabic RTL:

1. Open `/admin/catalog/import-rental-services`,
   `/admin/catalog/import-sale-services`, and
   `/admin/catalog/import-digital-services` (use the registered panel URLs if
   route prefixes differ). Confirm each heading is Arabic, the file control,
   template action, import action, success/error feedback, and table headings are
   localized, and the import action remains permission-protected.
2. Open the rental, sale, and digital service create/edit/list screens. Confirm
   translation tabs, media labels, currency/minor-unit helper text, and every
   status option/badge are Arabic; confirm no values such as `pending_review` or
   `Import Rental Services Page` appear.
3. Confirm the service name and vendor business name are the primary visible
   identities. Reveal the optional public-ID column and verify it is copyable,
   contains a ULID, and no numeric internal ID is displayed.

Repeat the same screens in English LTR and confirm equivalent English labels,
localized dates, keyboard focus, responsive desktop/mobile layout, empty/error
feedback, and that service names remain readable when long. Capture the final
URL, authenticated role, locale, visible heading/status text, and any Browser
failure. A server response or Livewire snapshot alone does not close this
Browser check.

## Status

`UX-SERVICE-001`: **PARTIAL**. The reported source-level localization and public
identity defects are addressed and covered by focused tests. Headed Browser
confirmation, responsive/empty/loading visual states, and PHPStan analysis of
Filament paths remain unverified or blocked by the repository configuration.
