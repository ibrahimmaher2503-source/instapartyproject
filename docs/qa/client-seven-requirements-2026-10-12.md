# InstaParty — Seven client requirements: implementation and QA

Date: 2026-10-12. Scope: local implementation, regression testing, browser QA and deployment preparation. Production was not modified.

## 1. Executive summary

- Seven requirements implemented; seven passed local functional browser checks using representative, isolated data.
- Original client screenshot acceptance remains blocked because `insta.docx` was not supplied or found in the checkout/attachments. The earlier category screenshot is a separate homepage requirement, whose existing CSS fix was preserved.
- Production acceptance remains pending explicit deployment authorization, deployment, and verification of the actual server/media. No claim of 100% client or production acceptance.
- Baseline relevant tests: 12 passed, 101 assertions. Final focused tests: 24 passed, 212 assertions.
- Full PHP regression first run: 559 passed, 4994 assertions. Final rerun: 561 passed, 5013 assertions, 537.76 seconds.
- Final Chromium E2E: 21 passed, 4.0 minutes. EN + AR at 390, 768, 1024, 1440 and 1920 pixels. Final run asserts no page JavaScript errors or unexpected HTTP failures; deliberate intercepted image 404 responses are permitted only in the broken-media test.
- Vite build passed. Manifest verified: all five entries exist. PHPStan: full final analysis passed with no errors.
- Changed-file Pint passed. Full Pint fails only on unchanged `app/Modules/Geography/Filament/Resources/CityResource.php`; its HEAD version independently reproduces the failure. It was not reformatted.
- No commit, push, merge or deployment performed. No schema changes or dependency additions.

## 2. Safe setup and working-tree boundaries

Starting branch: `main`. Starting HEAD and fetched `origin/main`: `c8882e45f895fe31245fd4e7e57147129d3ec544`. Executed `git fetch --no-prune`; created `fix/instaparty-client-reported-issues` as the requested development branch. HEAD remains unchanged because no commit was requested.

The existing dirty `resources/css/homepage.css` category-layout change was retained. Separately appearing `docs/qa-discovery/` files belong to other work and were neither edited nor included in this implementation. No Git cleanup was used.

PHP tests force SQLite `:memory:` via `phpunit.xml`. Browser tests use only `storage/client-requirements.sqlite` on port 8147, guarded before `migrate:fresh` and fixture creation. They do not reset the local application database or production. The normal local site remains on port 8000. The isolated fixture preview remains available at http://127.0.0.1:8147/en/vendors/qa-many (English) and /ar/vendors/qa-many (Arabic). The local application database currently has no vendor profiles: real-data populated vendor acceptance cannot be established there. Empty vendor index returned HTTP 200. Representative tests are explicitly fixtures, not production evidence.

## 3. Issue matrix

| Issue | Implemented | Backend verified | Frontend verified | Browser tested | Visual QA | Status |
|---|---|---|---|---|---|---|
| 01 Gallery/media | Yes | Portfolio + services, valid conversions/original fallback, missing files, ownership, >24 images | Empty/single/multiple states and runtime image failures | EN/AR responsive navigation, next/previous/thumbnails; intercepted 404 recovery | Local screenshots inspected; client reference unavailable | Local pass; production storage pending |
| 02 Vendor filter | Yes | Scoped type results, invalid input, pagination, disabled-digital existing gate | GET form, clear link, empty results | All three types, reload, pagination, clear at five widths | Local controls inspected; client reference unavailable | Local pass |
| 03 Names instead of count | Yes | Real displayable localized names; unique names across all pages | Numeric summary replaced; responsive wrapping | EN/AR names checked; mobile summary height regression | Mobile compression found and fixed; after screenshot inspected | Local pass |
| 04 Governorates | Yes | Primary/fallback + coverage, deduplicated, API names, eager-loading query regression | Profile, generic cards and distinct homepage cards updated | EN/AR profile/card location at all widths | Local EN/AR screenshots inspected | Local pass; production mapping pending |
| 05 Five items/row | Yes | Uses existing service query; page size 15 | 5 columns from 1280px; 3 at 1024; 2 at 640; 1 below | Computed columns verified at all five widths; no horizontal overflow | Five-column desktop and mobile screenshots inspected | Local pass; exact client reference breakpoint unconfirmed |
| 06 Rental count removed | Yes | Backend count data retained and existing type-count tests pass | Rental text has no numerical suffix; sale/digital counts retained | EN/AR rental badge output checked | Local badges inspected | Local pass |
| 07 Subcategory | Yes | Active matching child validation, combined type/vendor filters, pagination, old child URLs | Dependent options, stale-child clear, disabled empty selector, URL and filter chips | EN/AR selection, parent change, submit, reload, pagination | Desktop/mobile search screenshots captured and inspected | Local pass; populated real local hierarchy unavailable |

Visual QA here means inspection of the built local UI and its responsive behavior, not approval against unavailable `insta.docx`.

## 4. Root causes and changes

### 01 — One gallery source and actual storage checks

`VendorBrowsingRepository::portfolioFor()` previously aggregated only service images while the Blade controller read vendor-owned portfolio media. They now share one ordered source: vendor portfolio first, then galleries of displayable vendor-owned services. URLs are deduplicated. The existing API limit remains 24 while the web gallery can reach all valid images; `total_count` measures all valid unique images.

`PublicMediaUrl::for()` checks the configured conversion disk for a generated conversion, then the original disk. Missing conversions fall back to existing originals; missing media is excluded without fabricating images or deleting data. The generic service card uses the same safe selection for its medium image. Runtime failures in the vendor gallery skip the failed image and reveal an empty state when none remain. Broken thumbnails are hidden.

Files: `app/Modules/Shared/Application/Services/PublicMediaUrl.php`, `app/Modules/Discovery/Infrastructure/Repositories/VendorBrowsingRepository.php`, `app/Modules/Discovery/Http/Controllers/Web/VendorController.php`, `resources/views/storefront/vendors/show.blade.php`, `resources/js/storefront.js`, `resources/views/components/storefront/service-card.blade.php`, `lang/{en,ar}/storefront.php`.

Storage limitation: this checkout has a copied `public/storage` directory rather than a link. Browser setup copies only its own synthetic QA SVG assets to the corresponding public paths, preserving the directory. Production must serve originals and conversions from the configured public disk/storage URL. PHP disk existence does not prove CDN access; post-deployment HTTP verification remains necessary. Unrecoverable missing originals require a backup/re-upload on the real server. Runtime recovery prevents broken icons but cannot recover lost bytes.

### 02 — Connect existing vendor-scoped filter to profile

`VendorController::show()` validates canonical product types and passes them to existing `servicesFor()`. Cursor pagination uses 15 items, keeps `type`, and anchors to the service section. Blade renders a localized type selector, submit and clear controls, pagination and the existing empty result state. Disabled digital is rejected consistently with the public vendor API.

Files: VendorController, vendor show Blade, VendorBrowsingRepository relation loading. No new endpoint.

### 03 — Replace only the disputed summary

`serviceNamesFor()` reads all displayable services for the current vendor, localizes using existing `StorefrontText`, and removes duplicate names. A wrapping list replaces `data-vendor-services-count`; internal `services_count`, vendor-list counts and type counts remain. The names block occupies a full row so mobile adjacent summary cards cannot compress names into individual characters.

Files: VendorBrowsingRepository, vendor show Blade. Draft names remain private.

### 04 — One localized governorate mapping

`StorefrontText::vendorGovernorates()` combines primary governorate (primary-city relationship fallback) with coverage-area city governorates, removes duplicate public IDs and localizes names. Profile header/summary, generic vendor/service cards, distinct homepage featured-vendor/service components, search vendor option labels and service API `area_hint` use it. Vendor API resources add `governorates`, retaining existing `city` for compatibility.

Loaders now eager-load the nested paths in VendorBrowsingRepository, web/API vendor controllers, SearchServicesAction, ListPublishedServicesAction, ListVendorServicesAction, ShowPublishedServiceAction, ServiceRecommendationRepository and ServiceDiscoveryExtrasController. A collection test asserts zero new relationship queries while mapping coverage governorates for six services.

Files: the above classes, `BuildsServiceContract.php`, `VendorProfileResource.php`, `resources/views/components/{home,storefront}/*vendor*` and `*service*`, SearchController and vendor show Blade.

### 05 — Responsive vendor grid

Scoped `.sf-vendor-services-grid` rules in `resources/css/storefront.css` make five cards fit from 1280px, three from 1024px, two from 640px, one on mobile. `min-width:0`, compact card padding and long-text wrapping prevent overflow. No global search-grid redesign.

Files: storefront.css and vendor show Blade. Browser measures actual computed columns, not class names.

### 06 — Rental presentation only

Rental badge condition omits only its separator/count. Sale/digital counts and label translations remain. Internal full-vendor count calculations are unchanged.

File: vendor show Blade.

### 07 — Dependent public categories

SearchController validates `subcategory` against the active category list and selected parent. Its effective category code feeds the existing search DTO/query, vendor options and combined filters; no new search engine or schema is introduced. Old direct-child `category_slug` links normalize to parent plus child. Invalid, inactive or mismatched child selections fail validation.

The category selector lists roots. Child options are localized server-side, then updated from safely encoded JSON by the existing storefront script. Changing a parent clears the child and disables the selector if it has no children. Vendor options refresh using the selected child. Reload, query-state pagination and active-filter removal preserve expected state.

Files: `SearchController.php`, `resources/views/storefront/search.blade.php`, `resources/js/storefront.js`, `lang/{en,ar}/storefront.php`.

Existing public APIs are documented in `docs/api/vendor-public-presentation.md`, `.specify/memory/api-registry.md` and three Bruno requests. Vendor-owned portfolio items have nullable `service_public_id`; consumers must handle this association. No new API route was introduced.

## 5. Testing evidence

```powershell
# Baseline: 12 passed / 101 assertions
php vendor/bin/pest tests/Feature/VendorPublicGalleryTest.php tests/Feature/VendorServiceTypesTest.php tests/Feature/StorefrontSearchVendorOptionsTest.php tests/Feature/StorefrontSearchFallbackTest.php --bail --do-not-cache-result --no-coverage

# Focused final: 24 passed / 212 assertions
php vendor/bin/pest tests/Unit/VendorGovernoratePresentationTest.php tests/Feature/VendorClientRequirementsTest.php tests/Feature/StorefrontSubcategoryFilterTest.php tests/Feature/VendorPublicGalleryTest.php tests/Feature/VendorServiceTypesTest.php tests/Feature/StorefrontSearchVendorOptionsTest.php tests/Feature/StorefrontSearchGeographyTest.php tests/Feature/StorefrontSearchFallbackTest.php --bail --do-not-cache-result --no-coverage

# Full regression, final source
php vendor/bin/pest --bail --do-not-cache-result --no-coverage

# Actual browser interactions: 21 passed, 4.0 minutes
npx playwright test --config tests/e2e/client-requirements.config.ts --max-failures=1

npm run build
php vendor/bin/phpstan analyse --no-progress --memory-limit=2G --debug
php vendor/bin/pint --test
# Changed-file Pint also run and passed; full Pint baseline failure above

git diff --check
```

PHPStan needed 2G after an initial 128M exhaustion; default runtime environment initialization also waited on unavailable configured MySQL. Final invocation used the local SQLite connection and array cache as process-only overrides. This did not edit `.env`.

Test-first evidence: the new profile test first failed because `type=sale` returned rental results; the new subcategory test first failed because the selector was absent. Existing tests were updated for legitimate page-size 15 and actual media fixture file existence; missing files are no longer treated as valid gallery entries.

Early browser failures were investigated: fixture roles were missing, the fixture importer did not finish, a mobile selector matched both open and close buttons, and synthetic files were not in the copied public storage directory. These setup problems were corrected. The browser then exposed a real mobile names-layout issue, which was fixed and covered by an explicit height assertion. Final suite passes without retries or suppressed tests.

Command-output evidence is under ignored `storage/app/client-requirements-*`; browser trace/screenshots and `console-network` JSON attachments are under ignored `tests/e2e/.results-client-requirements` and `.report-client-requirements`. Application logs, secrets and database dumps are not part of the deliverable.

## 6. Visual evidence

All files below are actual screenshots of the served built app using the guarded browser fixture database. Synthetic SVG gallery media is explicitly test data, not fabricated business content. Service-card fallback artwork comes from existing application assets. Populated real-vendor screenshots cannot be captured from the current empty local vendor dataset.

- [English desktop](screenshots/client-requirements-2026-10-12/vendor-en-1440.png)
- [Arabic desktop](screenshots/client-requirements-2026-10-12/vendor-ar-1440.png)
- [English mobile](screenshots/client-requirements-2026-10-12/vendor-top-en-390.png)
- [Arabic mobile](screenshots/client-requirements-2026-10-12/vendor-top-ar-390.png)
- [English search](screenshots/client-requirements-2026-10-12/search-en-1440.png)
- [Arabic search](screenshots/client-requirements-2026-10-12/search-ar-390.png)

Full vendor/search captures exist for every tested locale and width. Existing audit serves as before-behavior evidence; original client vendor screenshots and corresponding before screenshots were unavailable. No screenshot-reference approval is claimed.

## 7. Independent QA

A separate read-only reviewer was dispatched through the requesting-code-review skill. It found and rechecked:

1. Web gallery default 24-image cap: corrected; >24 regression added.
2. Separate homepage components still displaying city: corrected; bilingual component tests added.
3. New shared location mapping causing lazy coverage queries in recommendation/similar/vendor/detail loaders: corrected; no-extra-query test added.

Final independent code verdict: no remaining important defects found in inspected filtering, pagination, gallery error/navigation logic or regression tests. The reviewer did not claim to have rerun automated/browser tests. Coordinator browser inspections found and fixed the mobile summary compression separately.

## 8. Deployment readiness and remaining gates

The frontend deliverable is the built `public/build` directory plus manifest; it is ignored in Git and must be packaged or rebuilt during deployment. Expected files: `storefront-CN47YQMI.css`, `storefront-j1QrlNhK.js`, `homepage-BqnupiKt.css`, plus the app entries recorded in `storage/app/client-requirements-assets.json`.

Dockerfile installs PHP dependencies and copies source but has no Node/Vite build stage. `docker-compose.prod.yml` references a prebuilt GHCR image and runs migrations/cache commands on startup. This is repository evidence only; the actual production deployment mechanism/version is unverified. No container/deploy/service reset command was run.

Deployment checklist:

- [ ] Resolve the pre-existing full Pint gate before a commit/release; do not bypass it.
- [ ] Receive explicit production deployment authorization and confirm the real deployment mechanism.
- [ ] Package PHP source and lockfile-based production dependencies; include verified Vite build/manifest atomically.
- [ ] Exclude test databases, fixture media, QA output, local `.env` and debug/testing configuration.
- [ ] Verify configured original and conversion disks, public storage routing/CDN URL, permissions and readability.
- [ ] Inspect the previously broken production image: restore/re-upload its original if missing; regenerate a conversion only when its original exists.
- [ ] Clear/rebuild application view/config caches using the site's approved release process; account for the existing 5-minute vendor API cache.
- [ ] Verify actual deployed commit and asset hashes; no new schema migration is required by these changes.
- [ ] Rerun all seven flows against authorized real production data for EN/AR, all widths, combined filters and missing-media scenarios.
- [ ] Compare against `insta.docx` and obtain client visual acceptance.
- [ ] Check remote storage latency: gallery resolution performs existence checks for valid conversions/originals; local disk timing does not establish S3/CDN performance.

## 9. Final acceptance checklist

- [x] ISSUE 01 — Gallery navigation, valid-media selection and empty/single/multiple behavior verified locally.
- [x] ISSUE 02 — Vendor-scoped filters, clear, reload and cursor pagination verified locally.
- [x] ISSUE 03 — Unique localized service names replace the disputed summary.
- [x] ISSUE 04 — Primary/coverage governorates mapped and displayed locally in both languages.
- [x] ISSUE 05 — Five-column vendor desktop grid verified at 1440/1920, responsive smaller widths.
- [x] ISSUE 06 — Rental badge numerical suffix removed, unrelated type counts retained.
- [x] ISSUE 07 — Dependent public subcategory filtering, validation and pagination verified.
- [x] Arabic/English verified with representative data.
- [x] Responsive QA and browser interactions passed locally.
- [x] Focused and full automated regression tests passed on final source; no new regression detected in the executed suites.
- [x] Build passed; Vite manifest files exist.
- [x] Independent code review completed and findings corrected.
- [ ] Full release gates green: pre-existing full Pint failure remains.
- [ ] Populated real production data/media verified.
- [ ] Matched original client `insta.docx` screenshots.
- [ ] Production readiness accepted after the listed external gates.
- [ ] Production deployed/verified: not authorized or performed.

## Follow-up — vendor filter visual polish

The user identified the filter form as visually poor at 706px. Only vendor show Blade and scoped storefront CSS changed: bordered surface, full-width styled select, consistent 48px controls, secondary clear button, aligned desktop controls and a separate select row on mobile. Arabic buttons stretch equally when the clear text wraps on narrow screens. Existing routes, validation, filter behavior and data remain unchanged.

Latest build passed; current storefront CSS is `storefront-CN47YQMI.css`. Actual browser checks passed in EN/AR at 320, 390, 640, 706, 768 and 1440px (12 cases), including equal button heights, alignment, no overflow and sale/clear interactions at 390/706. Screenshot evidence: `screenshots/client-requirements-2026-10-12/vendor-filter-{en,ar}-{width}.png`. Full PHP/21-case browser suite results above describe the earlier implementation snapshot; they were not repeated for this presentation-only change. `git diff --check` passed.

## Follow-up — approved vendor page UX reduction

The user approved the visual/UX audit recommendations. Presentation changes preserve existing backend counts, ownership queries, media source, product types and pagination. The public profile now has one compact identity/location header, a shorter full-width gallery that contains the whole image, one services heading, three real offered names with the complete remainder accessible through native details/summary, and direct filter navigation links using the existing type query. All restores the unfiltered list. Only offered types appear; the active link has aria-current=page. The redundant type-count/location cards, passive badges, duplicate services eyebrow and general-search detour were removed from this page.

The shared service card receives optional showVendor=false and compact=true only from the vendor page. Its defaults preserve other callers. Vendor cards no longer repeat the owning vendor/location/description; type, category, name, rating where present, category attributes, price and service-detail navigation remain. Five columns remain at the existing desktop breakpoint.

Validation: 14 relevant PHP tests passed (141 assertions), four existing public search regressions passed (23 assertions), updated 21-case Chromium suite passed (4.1m), latest frontend build passed, changed PHP Pint and git diff --check passed. Final browser checks additionally covered EN/AR at 390, 706, 768, 1024, 1440 and 1920px: correct columns, first service within 1000px, no horizontal overflow, full-image containment, native disclosure by keyboard, filter links by Enter and All reset. No page JavaScript errors. Full PHP/static-analysis suites were not repeated for this Blade/CSS presentation follow-up; earlier full results remain snapshot evidence.

Measured first service position: mobile 390px 1647→777px; tablet 706px 1496→816px; desktop 1440px 1510→872px. Desktop card height now 351px; page height 2355px on the current 15-card fixture. These measurements depend on data and fonts and are not universal guarantees. Gallery fixture photos remain synthetic QA assets.

Changed files for this follow-up: vendor show Blade, shared service-card Blade, storefront.css, EN/AR storefront translations, VendorClientRequirementsTest and client-requirements.spec.ts. Current asset: storefront-CN47YQMI.css. Before/after captures are preserved outside the checkout in C:/Users/N/.codex/visualizations/2026/10/12/01a13045-0c8d-72c1-8571-1f758ca177e5/vendor-ux-* (full and top, EN/AR 390/706/1440). Browser measurement evidence: storage/app/vendor-ux-metrics.json. No database/schema/dependency or production changes.

## Follow-up — typed search within a vendor

User requested removal of the More services offered disclosure and a text search. The disclosure and its unused translations/styles are removed; the short three-name summary remains. A localized GET search form accepts q, retains selected type and searches English/Arabic service names inside the existing vendor-scoped displayable query. Type links preserve q; cursor links preserve q/type; submitting a new search resets cursor. The clear link resets both filters. Invalid non-string/overlong input is rejected; whitespace-only input is unfiltered. No matching services uses the existing localized search-empty message. Existing API callers keep the unchanged default query through an optional fourth servicesFor argument.

Validation: 16 focused tests passed (183 assertions); six browser tests passed without retries (1.1m), EN/AR 390/706/1440px. Browser flow: submit by Enter, combine rental type, paginate, reload, sale/no-match state, reset, Arabic query and foreign-vendor exclusion; no unexpected HTTP errors or JavaScript errors in final run. Changed-file Pint, scoped PHPStan and git diff --check passed. Frontend build passed. An initial browser run failed because compiled views were cleared during its final request (filemtime stat failure); the complete six-case rerun passed after edits/cache work finished. No failing test was suppressed. Existing browser disclosure assertions were replaced by absence assertions. No new route, migration or API input. Full project suites were not rerun for this follow-up. Preview restored on port 8147.

Current CSS: storefront-CN47YQMI.css. Visual captures: external visualization folder vendor-text-search-{en,ar}-{390,706}.png. Command evidence: storage/app/vendor-text-search-*.
