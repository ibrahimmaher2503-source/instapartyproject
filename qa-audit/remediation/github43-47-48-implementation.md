# GitHub issues 43, 47, and 48 — admin form remediation

Checked 2026-10-13 against the live GitHub issue bodies and current authorized admin routes. All three issues were still open. The scoped feature test uses the isolated SQLite database selected by `phpunit.xml`.

## [Issue 43](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/43) — advertisement package create

Current issue reproduction: “Submit valid values twice; both 500; expect creation or field-level validation.”

The authorized `GET /admin/advertisement-packages/create` returned 200. Submitting valid English and Arabic names/descriptions plus placement, duration, price, and impression limit through the Livewire form failed before a package was written. SQLite reported `NOT NULL constraint failed: advertisement_packages.public_id`; the attempted translation payload was double nested as `name = {"en":{"en":"Summer Campaign","ar":"حملة الصيف"}}`.

The create page combined Filament's active-locale `Translatable` page concern with explicit `name.en`/`name.ar` fields. That concern treated the nested translations as one locale and its default record handler bypassed the existing `CreateAdvertisementPackageAction`, which supplies the required ULID. The regression failed before the fix and now passes, checking the HTTP route, successful Livewire submit, persisted EN/AR values, and a 26-character public ID.

## [Issue 47](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/47) — tax-rate create

Current issue reproduction: “Customer 5% and All 10%, Effective From populated; both fail and save nothing; expect valid creation/validation.”

The authorized `GET /admin/tax-rates/create` returned 200. Livewire submissions for the issue's Customer 5% and All 10% examples failed with `NOT NULL constraint failed: tax_rates.public_id`; the attempted name/description translations had the same extra locale nesting, and neither insert created a row.

The create page used the same incompatible locale concern and bypassed `CreateTaxRateAction` and its repository, which supplies the public ID. The regression failed before the fix and now passes for both examples, checking persisted EN/AR names, customer/all applicability, stored basis points (500 and 1000), and public IDs.

## [Issue 48](https://github.com/ibrahimmaher2503-source/instapartyproject/issues/48) — tax-rate edit translation hydration

Current issue reproduction: “Open /admin/tax-rates/2/edit; list name exists, edit names blank after reload; unchanged save requires Name EN; expect hydrated translated fields.”

The authorized edit route returned 200 for a persisted bilingual rate. Before the fix, the Livewire edit form hydrated `name.en` as `null` although the stored translation existed; submitting unchanged data therefore could not satisfy the required English field. The regression failed on that null form state.

The page's active-locale `Translatable` concern populated flat locale data while this resource defines fields under `name.en`, `name.ar`, `description.en`, and `description.ar`. The page now fills those nested fields from the model's stored translations and sends updates through the existing `UpdateTaxRateAction`. The green regression checks all four values before save and confirms both name and description translations remain persisted after save.

## Verification and remaining evidence

`php vendor/bin/pest tests/Feature/GitHubAdvertisingTaxAdminFormsTest.php` passed: 4 tests, 53 assertions. Before the implementation, the advertisement create, tax create, and tax edit tests failed with the database constraint or null hydration described above; the permission-gating test passed before and after.

The regressions cover authorized HTTP route responses and real Livewire form submissions against isolated SQLite. Browser interaction in an authenticated admin session, locale rendering, and hosted behavior remain unverified. No tax calculation, rate policy, schema, or production data changed.
