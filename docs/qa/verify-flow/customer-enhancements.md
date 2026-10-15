# Customer enhancement verification — 2026-10-13

Scope: local evidence for GitHub issues #142, #143, #144, #146, and #147. Focused PHP tests use in-memory SQLite; the parent-owned browser spec uses only the already-prepared disposable `storage/e2e.sqlite`. Production changes, gateway-backed payments/refunds, and external customer or vendor data are out of scope. Issues #141 and #145 are being verified by the Discovery owner and are not covered here.

## Results

| Issue | Result | Evidence and remaining limit |
| --- | --- | --- |
| #142 — customer invoice | **PASS in focused local tests; browser pending** | The owner-only route renders the existing booking invoice as printable HTML. Tests assert booking ownership, reference, actual item/delivery/adjustment totals, no unsupported tax details, no false paid status without a captured payment, captured/partial/full-refund states, and Pending/Processing refunds taking precedence over a stale paid booking projection, all in EN and AR. Customer output omits free-text transportation adjustment sources; the admin invoice retains them. The invoice was not exported to or downloaded as a PDF. |
| #143 — refund information before confirmation | **PASS in focused local tests; browser pending** | The cart reads the existing published `refund-policy` CMS record and links to its canonical locale route. EN/AR tests verify the CMS summary and link before submission; no new refund terms are asserted by the cart. |
| #144 — FAQ and customer support fallback | **PASS in focused local tests; browser pending** | The help page renders active FAQ categories/items from Support data in EN/AR and excludes inactive questions. Its form submits through the existing Support request/action, persists a real support ticket, and shows the resulting public reference. No fabricated email address, phone number, or off-site contact channel is presented. |
| #146 — vendor portfolio gallery | **PASS in focused local PHP tests; browser pending** | `VendorPublicGalleryTest` verifies authenticated upload/list/delete ownership and that both the public vendor page and service detail page show only the approved owner’s existing public portfolio images in EN/AR. It excludes another vendor’s media, unapproved vendor media, missing files, private source disks, and private conversion disks. |
| #147 — booking submitted notice | **PASS in focused local tests; browser pending** | Customer dispatch tests assert exactly one in-app, email, and push record per booking/customer/channel across event replay, with the public booking reference, actual pending status, and localized details URL. A failed push remains failed on replay and succeeds through the existing retry action without duplicating the other channels. This uses fake/local delivery, not provider delivery. |

The invoice status now reads unresolved refund presence from actual Pending/Processing refund rows associated with the booking’s captured payment rows. It does not depend on `bookings.payment_status` having been projected yet and does not change the payment lifecycle projector.

## Browser spec

Parent verification on 2026-10-13: the Chromium browser flow passed (1 test, 25.9 seconds; test body 11.1 seconds). It verified EN/AR help, a real persisted synthetic support ticket, one public portfolio image on vendor and service pages in both locales, the policy link in a nonempty cart, and the unpaid printable invoice opened from customer booking details in both locales. The spec now accepts canonical absolute URLs and deletes its booking fixtures before restricted service rows. Issues #142/#143/#144/#146 and feature-tested #147 were closed at the owner's explicit request; the closure comments state that new batch code remains local/unpublished and real provider delivery remains unverified.

`tests/e2e/qa-verify-customer-enhancements.spec.ts` is authored for the parent to run against the already prepared disposable `storage/e2e.sqlite` and local server. It uses uniquely keyed synthetic support, FAQ, invoice, vendor, service, and public-disk media fixtures and removes only those records/files; a canonical refund policy page or navigation entry is removed only if the spec created it. It covers the public help form in EN, the FAQ/form in AR, customer login, the canonical policy link in EN/AR, vendor portfolio on both vendor and service pages in EN/AR, and navigation from customer booking detail to an unpaid printable invoice in EN/AR. The customer password is test-only and has no external account use.

The spec is listed successfully without running setup or browser hooks:

```powershell
npx --no-install playwright test tests/e2e/qa-verify-customer-enhancements.spec.ts --project=chromium --no-deps --list
```

Result: **PASS — one test discovered**. The browser flow itself has **not been run by this agent**. The parent owns browser execution; do not run the global Playwright setup or rebuild/reset the E2E database to validate this spec.

## Focused PHP evidence

| Command | Result |
| --- | --- |
| `php vendor/bin/pest tests/Feature/QaVerifyCustomerEnhancementsTest.php --bail` | PASS — 18 tests, 80 assertions |
| `php vendor/bin/pest tests/Feature/BookingInvoiceTest.php --bail` | PASS — 2 tests, 16 assertions; admin document keeps internal adjustment source |
| `php vendor/bin/pest tests/Feature/GitHubBookingInvoiceAmountsTest.php --bail` | PASS — 10 tests, 63 assertions; totals, captured payment, partial refund, and full refund regression coverage |
| `php vendor/bin/pest tests/Feature/VendorPublicGalleryTest.php --bail` | PASS — 6 tests, 62 assertions |
| `php vendor/bin/pest tests/Feature/QaVerifyCustomerBookingNotificationTest.php --bail` | PASS — 3 tests, 38 assertions |
| Combined focused invoice, gallery, support, refund lifecycle, and notification run with `--bail` | PASS — 60 tests, 362 assertions |
| Pint `--test` on the changed PHP files | PASS |
| PHPStan on the new portfolio reader and its two storefront controllers (`--memory-limit=1G`) | PASS — no errors |
| `git diff --check` | PASS |

These local checks include automated Chromium interaction, but do not establish production parity or real email/push delivery. All five issues were closed for requested local acceptance; publication and external delivery remain separate outstanding work.
