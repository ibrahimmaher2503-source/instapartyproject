# Admin UX verification

## Scope

This evidence covers admin navigation favorites and recent pages, the booking monitor's vendor/date/amount filters and saved views, compact booking cards, badge contrast, and English/Arabic direction and phone input behavior. Browser acceptance is pending; this does not claim hosted or production verification.

## Behavior

- Navigation favorites and recently opened pages are kept in browser storage under the signed-in admin's own key. Only currently rendered Filament navigation links are eligible, so hidden or inaccessible destinations are discarded. Both sets are keyboard operable and labeled in English and Arabic.
- The booking monitor adds vendor names and event start dates, with event-date range, vendor, and total-amount filters. Amount bounds are parsed into Brick Money integer minor units. Saved filter views use a separate per-admin browser-storage key and can be named, applied, replaced, or deleted.
- Booking monitor records use a single-column content-card layout on narrow screens. Status badge colors are adjusted for light and dark surfaces.
- Filament's language switch uses native locale names. A selected locale in the session or language-switch cookie takes precedence over the browser language, while a valid ?lang= query still overrides it. Telephone inputs remain left-to-right and isolated in Arabic.

## Automated evidence

The focused PHP feature test covers combined date/minor-unit amount/vendor filtering, grouped vendor choices across repeated allocations, per-admin shortcuts and saved-view rendering in both locales, selected-locale precedence over Accept-Language, and the explicit language query overriding a previously saved locale on the booking create page.

Focused validation in the local worktree:

~~~text
php artisan test tests/Feature/QaVerifyAdminUxTest.php --compact
Tests: 6 passed (33 assertions)

npx playwright test --no-deps --list tests/e2e/qa-verify-admin-ux.spec.ts
Total: 3 tests in 1 file

php -l app/Modules/Identity/Http/Middleware/SetLocaleMiddleware.php
No syntax errors detected

npm run build
Passed; Vite retained existing runtime references for missing image/font assets.
~~~

The Playwright spec covers keyboard favorites and saved-view actions, per-user persistence and path sanitization, filter application, measured 390px overflow/card actions, both text directions, computed status-badge contrast in light/dark mode, native locale labels, and phone input direction. The parent task runner owns browser execution; this document does not claim the spec has run in a browser.

## Limits

The focused PHP feature test and asset build passed. Browser interaction, the full test suite, PHPStan, and final shared-worktree formatting checks are for the parent task runner. No external provider, hosted environment, or production state was changed or verified.
