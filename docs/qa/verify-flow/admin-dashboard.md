# Admin homepage verification (#100)

Base: `4b086b58a23cf122bfb5667e9aacce0137665899`. Changes are local on `codex/qa-verify-flows`; no deployment or GitHub closure is implied.

A nonempty booking chart reproduced two exceptions on `/admin`: Eloquent hydrated the aggregate `total` alias through the booking money cast without its minor-unit/currency columns, then the chart view called an undefined `$chartPoints` variable. The aggregate now uses the base query builder. The chart partial computes SVG coordinates using one maximum across its series, preserving comparison between series.

`php vendor/bin/pest tests/Feature/QaVerifyAdminDashboardTest.php --bail` passed: 1 test, 3 assertions. The test creates two current bookings, verifies their seven-day counts and requests the authenticated admin homepage. Both exceptions were observed before their fixes.

`npx playwright test tests/e2e/admin-audit.spec.ts --project=chromium --no-deps --grep 'admin acceptance screens'` passed: 1 browser test in 19.3 seconds. It opened 12 admin screens, including the homepage, and checked HTTP 200, absence of server-error text and untranslated keys. Fixtures used the disposable `storage/e2e.sqlite` database and local notification sinks. This proves local page rendering; production deployment and production data remain unverified.

Repository-wide PHPStan passed with `--memory-limit=1G`; its default 128 MB invocation exhausted memory. Full Pest and final publication gates remain in progress for the broader batch.
