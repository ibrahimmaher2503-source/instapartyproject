# Wave 6 — Finance and moderation UI results

Date: 2026-09-13  
Scope: `UX-MODFIN-001` only. `UX-SERVICE-001` was not changed because the
service import pages are a separate Catalog surface and no direct dependency
was found in this slice.

## Finding and fix

`DisputeOversightPage` rendered a raw page heading, refund status and reason
code, hard-coded EGP arithmetic, unlocalized JSON notes/vendor names, and raw
Carbon dates. `ReviewModerationPage` also relied on raw review type, locale and
moderation status values and inherited the English page heading.

The presentation fix adds localized page titles and enum labels, adds the
missing `mixed` review locale in both languages, formats persisted minor-unit
amounts with Brick Money and the stored currency, formats dates in the active
locale, selects refund currency/reason code for display, and chooses the active
locale for multilingual notes and vendor names. Unknown vendor identity now
uses a localized placeholder instead of an internal numeric ID. Existing
actions, authorization, financial state transitions, ledger history and schema
were not changed.

The finance identity presenter had three missing English translation keys in
the current checkout (`settlement.identity.legacy_unknown`,
`settlement.columns.resource`, `settlement.columns.owner`). English and Arabic
identity/column translations were added after the first full Pest attempt
reported the missing English keys. The corresponding payments keys were
already present in the current checkout and were confirmed through Laravel's
runtime translator.

## Evidence

```text
php vendor/bin/pest tests/Feature/FinanceUiLocalizationTest.php --bail
PASS — 2 tests, 16 assertions, 11.89 seconds

npx playwright test tests/e2e/admin-audit.spec.ts -g "moderation and dispute screens localize financial states" --workers=1 --timeout=120000
PASS — setup plus 1 feature test (2/2), 30.7 seconds

php vendor/bin/pint --test app/Modules/Reviews/Filament/Pages/ReviewModerationPage.php app/Modules/Settlement/Filament/Pages/DisputeOversightPage.php tests/Feature/FinanceUiLocalizationTest.php
PASS
```

The focused browser run used a fresh isolated SQLite database and a real
Chromium page. It verified Arabic titles, moderation/refund states, the
localized refund reason and note, and the absence of raw `customer_request`.

The first post-change full Pest attempt stopped at
`FilamentTranslationCoverageTest`:

```text
FAIL — 57 tests passed, 899 assertions, 87 pending before --bail stopped
Missing en translations: settlement.identity.legacy_unknown,
settlement.columns.resource, settlement.columns.owner
```

The missing keys were then added in both locales. The rerun completed with:

```text
php vendor/bin/pest --bail
PASS — 149 tests, 1,330 assertions, 61.82 seconds

npm run e2e -- --timeout=120000 --workers=1
PASS — 17 tests, 1.2 minutes

php vendor/bin/pint --test [Wave 6 PHP files]
PASS
```

The full Browser run used a fresh isolated SQLite database and real Chromium;
it included setup, all admin checks, the Arabic finance/moderation journey,
import, support, webhook, inventory/document and storefront checks.

PHPStan's configured `excludePaths` omit `app/Modules/*/Filament/**/*`; a
targeted invocation therefore returned `No files found to analyse`. No
baseline or ignore was changed. The existing 13 PHPStan baseline findings
remain documented in the prior wave report.

## Status

`UX-MODFIN-001` is **OPEN/PARTIAL**. The two reported presentation paths are
covered, but the complete finance surface, responsive/empty/loading states,
diagnostic event presentation, concurrent flows and missing product/contract
decisions were not proven. `UX-SERVICE-001` remains **OPEN/UNVERIFIED** in this
wave. No production data, `.env`, packages, migrations or ledger rows were
changed.
