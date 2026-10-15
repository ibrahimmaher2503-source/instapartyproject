# Identity verification evidence — 2026-10-13

Scope: GitHub issues #153, #155, #182, #183, #1, #3, #30, #100, #32, and #134. Local tests and the disposable browser database are in scope. Production changes, production credentials, real email/SMS delivery, charges, payouts, and customer/vendor records are out of scope.

## Checklist

| Issue | Result | Evidence and remaining limit |
| --- | --- | --- |
| #153 — new customer activation | **PASS locally; real SMS delivery remains blocked** | Focused feature tests and the final browser customer test pass. In the browser, a fresh guest registration reached `/en/auth/register`, the local OTP stub accepted the submitted six-digit code, the account profile showed the new name, and API login returned HTTP 200 with a token-present result. The API request used the existing same-origin CSRF meta token; only its availability boolean was recorded. `StubOtpGateway` is local-only and accepts any six-digit code; this proves neither actual SMS delivery nor provider expiry/cooldown behavior. |
| #155 — payload privacy and over-fetching | **PARTIAL; representative local cross-role audit passed** | The bounded browser run measured representative public/customer/vendor/admin pages and same-origin network/API responses. Disposable customer/vendor ownership markers stayed private, credential/token values were not printed, and cross-role API calls returned 403. This is representative coverage, not an exhaustive inventory of every route or serialized field. Each vendor panel capture also recorded four failed same-origin requests; the route-only diagnostic rerun is pending before they can be classified. |
| #182 — slow admin modals | **PASS locally; production retest pending** | The approval queue checklist test evaluates three records in at most six queries. The admin browser test passed and measured click-to-modal-ready at 2,341 ms on the queue, 1,800 ms on review detail, and 1,312 ms on the vendor-profile list. These local timings do not establish the production cause or post-deploy behavior. |
| #183 — admin vendor approval | **PASS locally; production retest pending** | `VendorApprovalLifecycleTest` verifies eligibility, bilingual actionable failures, successful queue/review decisions, and separate account/type approvals. `Batch07ApprovalResponsivenessTest` confirms mounted queue-action success and bounded checklist queries. The latest admin browser test passed the queue list, review detail, vendor-profile list, and ineligible-profile assertions. Account approval alone intentionally grants no product type; type grants remain explicit. |
| #1 — verification/reset email delivery | **BLOCKED** | `AuthEmailDeliveryReadinessTest` checks closed behavior, the local log mailer path, and bounded SMTP timeout. No inbox delivery or production mail transport/queue-worker verification occurred. |
| #3 — weak production admin credential | **BLOCKED** | No production credential rotation, session invalidation, MFA test, or access-log review was performed. |
| #30 — communication-provider readiness | **BLOCKED** | Local OTP stubbing and mail readiness tests do not establish SMS, mail, FCM, WhatsApp, or Mailchimp provider configuration or delivery. |
| #100 — admin homepage 500 | **IN PROGRESS — parent-owned** | The parent reproduced the admin dashboard failure locally and isolated aggregate hydration of a money cast on an aliased total. The parent owns the fix and regression test; final focused test/browser results are pending. |
| #32 — admin HTML/network payload size | **MEASURED LOCALLY; budget comparison pending** | Admin Users rendered 758,706 decoded document bytes (769,939 total decoded document/XHR bytes across 13 same-origin requests); Admin Vendor Profiles rendered 745,034 bytes (756,277 total across 14 requests). The lists showed 25 rows each on page one; Livewire pagination changed the vendor list to 15 rows using one 372,667-byte update. No approved performance budget or latency failure is established; modal timing (#182) stays separate. |
| #134 — customer pages/tutorials/permission count | **IMPLEMENTED LOCALLY; browser run pending** | The approved customer scope now has owned address CRUD, normalized saved searches, read-only loyalty balances/history, and a chats inbox linked to real service inquiry threads. Booking chat status is shown only when Firebase is configured; otherwise the page explains its current availability. The empty tutorial launcher is hidden. Focused feature tests pass; the new account browser spec is awaiting the parent-owned run. Source explicitly excludes `impersonate_vendor` from the admin role while super-admin receives all permissions; live database counts were not measured and were not changed. |

## Identity changes and evidence

GitHub update, 2026-10-13: #182 and #183 were closed at the owner's request after the recorded local approval/browser measurements and passing focused lifecycle tests. Closure comments explicitly preserve the production/provider verification limit. The fresh closure regression run passed 50 tests / 399 assertions across approval and the other selected local-acceptance issues; this is not the repository-wide publication gate.

`LoginAction` now refuses password sign-in for a customer whose `phone_verified_at` is null. The check runs after credential and suspension checks and before login metadata or token creation. It is limited to customer-role accounts; vendor onboarding/sign-in, privileged admin isolation, and the verification/recovery route remain separate. The new EN/AR message tells the customer to return to the verification page to request another code.

The tests prove that unverified customer credentials do not create a web session, API token, or `last_login_at`; the same localized validation error is returned in EN and AR. A separate fake OTP gateway exercises the existing session-bound resend/verification route, rejects a wrong code, activates the user on the sent code, and reaches the account page. Existing duplicate-phone/email checks remain in place. These checks use in-memory SQLite and array cache/mail/session/queue settings.

The privacy tests inspect only representative Identity API responses. They do not complete issue #155’s cross-role network and list-payload checklist. The vendor-profile payload omits internal IDs, national ID, tax ID, commercial registration, and bank IBAN; the customer profile response is scoped to the authenticated owner. No secret values are copied into this report.

Approval checklist failures include the translated missing/expired document details. A stale test expectation omitted the existing `documents_need_attention` wrapper; the assertion now checks the actual wrapped, actionable message. No approval action source change was required. Approval and product-type grants stay separate in code and tests.

## Browser fixture and runner requirements

`tests/e2e/qa-verify-identity.spec.ts` creates unique synthetic records in `storage/e2e.sqlite` and removes only those records afterward. It expects the parent’s existing one-time E2E setup and authenticated admin storage state; run the spec with `--no-deps` and do not rerun the global setup or database reset. It uses `APP_ENV=testing`, SQLite, `SCOUT_DRIVER=null`, array cache/session/mail/queue for fixture commands, and the existing web server’s local `MAIL_MAILER=log` sink. No provider credentials or real delivery are used.

The admin fixture has `view_any_vendor::profile`, `view_vendor::profile`, and `approve_vendor_profile` permissions. Three pending vendors have verified contact data, complete EN/AR individual-business fields, matching Cairo geography, banking fields, one business-hours row, a coverage area, and approved non-expired National ID and IBAN Proof documents. A fourth fixture omits business hours to prove the UI reports an actionable eligibility error and preserves pending status. Each approval is a separate fixture so each UI surface exercises its real mounted action.

The customer fixture uses a fresh email/phone, accepted terms, a separate guest browser context, the storefront registration form, the local testing OTP stub’s resend action, and the `/en/auth/verify` path. The separate browser test submits the test code and checks account access plus API login. Each page, form, OTP, account, and API operation has a bounded step with a static label; logs contain only the label and outcome. The API request includes the existing same-origin CSRF meta token; the test reports only whether it was available. The stub accepts any six-digit code and logs only to local application logging; successful local activation is not a delivery receipt. No OTP, token, cookie, credential, or provider log is printed by the test.

Browser command for the parent after the existing setup/auth state is ready:

```powershell
npx playwright test tests/e2e/qa-verify-identity.spec.ts --project=chromium --no-deps
```

The customer account capability browser flow is in `tests/e2e/qa-verify-customer-account-capabilities.spec.ts`. It uses a fresh customer context, unique local fixtures, and removes only rows tied to that fixture email/vendor slug. It covers address creation/edit, saving and rerunning a search, deleting the saved search, viewing loyalty history, opening the existing inquiry thread, and EN/AR account navigation. Discovery has passed; the parent owns the actual browser execution against the already-running local E2E database.

```powershell
npx playwright test tests/e2e/qa-verify-customer-account-capabilities.spec.ts --project=chromium --no-deps
```

The spec contains separate admin-approval and customer-activation tests, with bounded static step labels. Playwright discovery passes and lists both tests. Parent-executed browser runs passed the admin flow through queue, review detail, vendor-profile list, and the ineligible-profile error path. Click-to-modal-ready timings were 2,341 ms, 1,800 ms, and 1,312 ms respectively. The final customer run passed registration, the local OTP stub path, account profile identity, and API login in 35.5 seconds total (22.2 seconds reported for the customer test). Earlier diagnostics found that the customer context inherited admin storage state, then that same-origin API login needed the page’s standard CSRF header; both issues were corrected without weakening assertions. Browser execution and the tests use only the parent’s existing disposable E2E setup/database; no database reset or external provider was used.

## Payload privacy and size audit

`tests/e2e/qa-verify-payload-privacy.spec.ts` is a separate bounded browser audit for #155/#32. It seeds uniquely identified synthetic customer/vendor accounts in the existing disposable E2E database, creates 14 and 8 customer-owned address rows, and enough approved synthetic vendor profiles to exercise public pagination and admin list pagination. It captures same-origin public/customer/vendor/admin page and Livewire response sizes, request counts, response field names, table/card row counts, and pagination behavior; bearer-authenticated API responses cover registration, account profile/read-update, owned address lists, vendor identity/profile, and public vendor profile. Redacted marker checks verify customer/vendor isolation, omission of identity/banking/authentication fields from role-facing API responses, and cross-role denials. The browser/page report contains safe labels, field names, sizes, counts, statuses, and booleans only; response bodies and credential/token/cookie values are inspected in memory and not attached or printed.

The parent-executed Chromium audit passed its assertions (45.7 seconds total; 39.8 seconds for the test). Public vendor pagination returned 12 cards on page one and 3 on page two for 15 disposable profiles, with no other-account or private vendor markers present. Customer A/B profile pages each returned 200 and contained no other customer's name, email, or phone; customer-owned address reads, account update, registration, and API login returned 200/201 as expected. Vendor identity/profile APIs and public vendor profile returned 200, while customer-token-to-vendor and vendor-token-to-customer requests returned 403. The admin vendor list's Livewire page transition returned 200 and changed the first row. Admin user/vendor list checks found no synthetic password or token values. Four same-origin requests failed during each vendor-panel capture despite the final page response returning 200; the pending rerun adds only normalized route/method/resource labels so those requests can be classified. Do not treat the panel failure count as zero or infer a product regression until those endpoints are reviewed. Captured byte counts are decoded in-memory response sizes, not transferred compressed bytes. This is representative evidence only, not every route, and no approved budget or production comparison was supplied.

```powershell
npx playwright test tests/e2e/qa-verify-payload-privacy.spec.ts --project=chromium --no-deps
```

## Issue #134 source review and implementation

### Fresh response audit and issue #155 closure

The parent reran the actual browser response audit on 2026-10-13: 1 Chromium test passed in 51.2 seconds (45.6-second body), log `storage/logs/qa-privacy-flow-review.txt`. Focused privacy, vendor-registration privacy and public/admin authentication separation passed 23 tests / 170 assertions in 21.04 seconds, log `storage/logs/qa-flow-privacy-feature-review.txt`. The owner-requested local verification issue #155 is closed; broader payload-size issue #32 remains open.

The four cancelled requests on each vendor profile are now classified as `POST /livewire/update` with `net::ERR_ABORTED`; all captures ended with zero pending requests and a final 200 profile response. They are disclosed as cancellations, not counted as completed requests. Public pagination showed 12 then 3 cards; admin pagination showed 25 then 18 rows and a changed first row. Admin users/vendor document sizes were 732643/725113 decoded bytes; the admin vendor pagination response was 429815 bytes. These are representative local measurements, not compressed transfer sizes, a performance budget or production proof. Source publication and final full-suite gates remain pending.

The customer account menu and module routes now expose the four approved surfaces. Identity address forms call the existing add/update/default actions, use public city/address IDs, and scope reads and mutations to the signed-in customer. Discovery saves only its supported normalized search filters and scopes deletion to the owner. Loyalty uses the existing balance action plus the customer’s append-only ledger history; it does not add redemption or earning rules. The Communication inbox links customers into their actual pre-booking inquiry conversations. Confirmed-booking chat entries show status and the owned booking detail link only when Firebase is configured; no new message-posting client or fabricated Firebase delivery behavior is claimed.

`tests/Feature/CustomerAccountCapabilitiesTest.php` covers address create/update/default/delete and foreign-owner rejection, saved-search normalization and owner isolation, read-only loyalty history, and own-versus-other customer chat records in both languages. The new `tests/e2e/qa-verify-customer-account-capabilities.spec.ts` exercises the storefront forms, saved-search rerun/delete, loyalty history, and a real inquiry reply using unique synthetic fixtures. Its Playwright discovery passes; browser execution is pending with the parent-owned E2E server and database.

The header launcher now returns no markup when the viewer has no permitted tutorial flows. The tutorial page remains available with its existing empty-state explanation. No flow content was fabricated because `docs/admin-flows/` contains no approved flow definitions.

`database/seeders/IdentityRolesSeeder.php` assigns `super_admin` all permissions and synchronizes `admin` to every permission except `impersonate_vendor`. This is the source-level explanation for the reported one-permission difference; it does not prove the current database has exactly those role assignments. No permission grant was added or removed. The actual runtime counts remain unverified because this pass did not reseed or inspect the parent-owned browser database.

## Focused local tests

All commands ran with `--bail`; tests use the repository’s in-memory SQLite and array test configuration.

| Command | Result |
| --- | --- |
| `php vendor/bin/pest tests/Feature/QaVerifyIdentityActivationTest.php --bail` | PASS — 5 tests, 73 assertions |
| `php vendor/bin/pest tests/Feature/QaVerifyIdentityPrivacyTest.php --bail` | PASS — 3 tests, 49 assertions |
| `php vendor/bin/pest tests/Feature/AdminPublicAuthenticationIsolationTest.php --bail` | PASS — 14 tests, 68 assertions |
| `php vendor/bin/pest tests/Feature/CustomerRegistrationTest.php --bail` | PASS — 2 tests, 14 assertions |
| `php vendor/bin/pest tests/Feature/VendorRegistrationPrivacyTest.php --bail` | PASS — 6 tests, 53 assertions |
| `php vendor/bin/pest tests/Feature/VendorApprovalLifecycleTest.php --bail` | PASS — 20 tests, 200 assertions |
| `php vendor/bin/pest tests/Feature/Batch07ApprovalResponsivenessTest.php --bail` | PASS — 2 tests, 16 assertions |
| `php vendor/bin/pest tests/Feature/AuthEmailDeliveryReadinessTest.php --bail` | PASS — 4 tests, 14 assertions |
| `php artisan test --compact tests/Feature/CustomerAccountCapabilitiesTest.php --bail` | PASS — 10 tests, 88 assertions |
| `npx playwright test tests/e2e/qa-verify-identity.spec.ts --project=chromium --no-deps --list` | PASS — two tests discovered; browser flows are evidenced separately above |
| `npx playwright test tests/e2e/qa-verify-payload-privacy.spec.ts --project=chromium --no-deps --list` | PASS — one audit test discovered |
| `npx playwright test tests/e2e/qa-verify-customer-account-capabilities.spec.ts --project=chromium --no-deps --list` | PASS — one test discovered; browser execution pending |

Focused PHP total: **66 passing tests, 575 assertions**. A separate run of `VendorRegistrationLifecycleTest.php --bail` passed 12 tests (56 assertions) before an unrelated route-registration error stopped its thirteenth test: `Invalid route action: [App\Modules\Booking\Http\Controllers\Web\CustomerBookingInvoiceController]`. This is not an Identity failure and was handed to the parent for cross-module triage.
