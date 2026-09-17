# InstaParty admin audit closure — 2026-09-10

## Decision

**Checkout status: LOCAL VERIFICATION COMPLETE.** All twelve numbered report findings are closed in code and the isolated test environment. The public test site still serves the previous build, so production acceptance requires deployment followed by the same browser suite against `https://instaparty.online`.

## Finding ledger

| ID | Status | Closure evidence |
|---|---|---|
| IP-QA-001 | PASS | Occasion create route renders and the bilingual Livewire create flow persists EN/AR independently. |
| IP-QA-002 | PASS | Category create route renders; parent/type schema and bilingual create flow pass. |
| IP-QA-003 | PASS | Loyalty program create route renders and creates a vendor-bound bilingual program. |
| IP-QA-004 | PASS | Rental, sale, and digital service searches match partial Arabic and English JSON translations. |
| IP-QA-005 | PASS | Service forms use independent `name.en`, `name.ar`, description, and short-description state paths with round-trip assertions. |
| IP-QA-006 | PASS | Admin and vendor error pages resolve their own panel dashboard URLs. |
| IP-QA-007 | PASS | Browser and PHP gates reject mojibake/`????`; the test database and connection use Unicode-safe values. |
| IP-QA-008 | PASS | Loyalty labels are complete in EN/AR; the all-Filament static-key gate prevents raw keys. |
| IP-QA-009 | PASS | Directly rendered enum labels use localized `HasLabel`; the affected catalog, booking, payment, and settlement screens pass both locales. |
| IP-QA-010 | PASS | Service category fields use server-side search with `optionsLimit(50)` and no preload. With 301 categories, the initial page omitted the marker category and rendered in 2.50 seconds locally. |
| IP-QA-011 | PASS | `booking:expire-stale-vendor-reviews` is idempotent, enabled by default, scheduled every minute, bounded against event start, and supports `BOOKING_VENDOR_REVIEW_EXPIRY_ENABLED=false` as a kill switch. |
| IP-QA-012 | PASS | Dashboard labels now distinguish captured payments, net collected, refunds, commission, and their formulas. |

## Shared UI improvements

- Admin and vendor panels use Teal as the single primary color with fixed Green/Amber/Red/Blue semantics and Slate neutrals.
- Cairo handles Arabic, Inter/system fonts handle Latin text, focus rings are visible, controls meet a 44px target, monetary values use tabular numerals, table headers are sticky, and RTL email/URL fields keep readable direction.
- Navigation is desktop-collapsible; inactive groups are collapsed explicitly while the active group remains open.
- Forms and tables use shared surface, border, spacing, responsive, and contrast tokens from `resources/css/app.css`.

## Verification

- `php vendor/bin/pest --bail`: **58 passed, 947 assertions**.
- `npm run e2e`: **3 passed** in Chromium, including isolated database/setup, admin create forms, payments, booking modifications, settlement runs, Arabic RTL, and mobile layout. The suite owns port `8139`, avoiding any existing local server.
- `npm run build`: **PASS**. Five existing storefront image URLs remain runtime-resolved warnings.
- Targeted PHPStan on supported changed provider/domain code with 1 GB memory: **PASS**. Filament resources are excluded by the repository PHPStan configuration; they are covered here by syntax, Pint, and browser acceptance checks.
- Full PHPStan: **BLOCKED BY EXISTING DEBT** with more than 1,000 errors across unrelated modules; no baseline or ignore was added.
- Full Pint check: **BLOCKED BY EXISTING DEBT** across unrelated files. All files changed for this closure were formatted with Pint.
- Local browser screenshots and JSON evidence: `storage/app/admin-uat-desktop.png`, `storage/app/admin-uat-mobile.png`, and `storage/app/admin-browser-uat.json`.

The report's proposed multi-actor vendor-to-settlement journey is broader than this admin regression suite. Existing PHP coverage verifies vendor onboarding, all three service types, and moderation. Payment, fulfillment, and settlement still require a separate end-to-end acceptance run after deployment; they are not represented as passed here.

## Live boundary

Read-only inspection of `https://instaparty.online/admin` showed the old form state paths, raw English enum labels, and the prior expanded navigation. Do not mark the public environment accepted until the prepared checkout is deployed, caches are rebuilt, the scheduler/queue are confirmed running, and `npm run e2e` is repeated against the live URL with approved test credentials.
