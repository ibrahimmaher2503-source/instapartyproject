# Wave 7 Customer Purchase Results

Date: 2026-09-13  
Issue: storefront customer purchase blocker observed at `/en/checkout`  
Status: **PASS for the route and request-contract diagnosis / PARTIAL for full purchase acceptance**

## Confirmed contract

The reported browser sequence reached `/en/search` and `/en/cart` with HTTP
200, then requested `/en/checkout` and received HTTP 404. The route inventory
confirms that this is an unsupported storefront URL: there is no
`storefront.checkout` route and no checkout Blade view. No `/en/checkout` route
was added.

The supported customer path is:

1. `GET /{locale}/search` to browse.
2. `GET /{locale}/services/{servicePublicId}` for a published service.
3. Authenticated `GET /{locale}/wizard?service={servicePublicId}`.
4. Authenticated customer `POST /{locale}/cart/setup`.
5. Authenticated `GET /{locale}/cart`.
6. Authenticated customer `POST /{locale}/cart/{bookingPublicId}/submit`.
7. After the draft has passed review, vendor acceptance moves the booking to
   `confirmed`; only then can the customer call payment initiation.

Checkout review is a pre-submit API check. Its service returns
`checks.is_draft`, and the focused test confirms that it is `true` for a draft
and `false` after the booking is moved to vendor review. Calling it after the
storefront submit therefore cannot be used as the next checkout step. Payment
initiation also rejects every booking whose lifecycle is not `confirmed`.

## Request, auth, and idempotency fixture

The wizard renders a CSRF protected `POST` form to `/en/cart/setup`. A valid
isolated browser form submission needs the following fields (the selected city
must be the city's public ULID):

```text
service_id={published service public_id}
occasion={existing occasion code}
event_starts_at=2026-09-20T18:00
event_ends_at=2026-09-20T22:00
address[city_id]={existing city public_id}
address[address_line]=12 Example Street
address[recipient_name]=QA Customer
address[recipient_phone_e164]=+201000000000
guest_count=10
celebrant_name=QA Event
quantity=1
```

The browser carries its authenticated session cookie and the hidden `_token`
through the `web` middleware. `auth:sanctum` and `role:customer` protect the
cart mutations. The storefront submit action generates its own UUID
idempotency key and stores the request as `bookings.submit`; the HTML form does
not supply an `Idempotency-Key` header.

Use the same authenticated customer session and `bookingPublicId` for the API
review request. Review is a POST with an empty body, `Accept: application/json`,
and the existing Sanctum customer credentials. It is protected by
`auth:sanctum`, `role:customer`, `ensure.account.active`, and locale handling;
it does not require an idempotency key because it is read-only.

After vendor acceptance has produced `confirmed`, payment initiation uses:

```http
POST /api/v1/customer/bookings/{bookingPublicId}/payments
Accept: application/json
Accept-Language: en
Idempotency-Key: {stable unique key for this request}
Content-Type: application/json

{"method":"card"}
```

The payment route is protected by `auth:sanctum`, `role:customer`,
`ensure.account.active`, and the HTTP idempotency middleware. The server derives
the amount and currency from the booking's integer minor-unit total; clients do
not send an amount. Repeating the same key with the same customer, route, and
body returns the cached response. Reusing it with different request content,
another customer, or another route returns 409.

The current Browser evidence matches these guards: after cart submit,
`checkout-review` returned HTTP 200 with `ready_to_submit=false`, and payment
returned HTTP 422 before the gateway because the booking was still in vendor
review. This is the expected response for that sequence.

## Vendor acceptance fixture

The existing vendor review workflow must run between cart submit and payment.
The API route is:

```http
POST /api/v1/vendor/booking-vendors/{bookingVendorPublicId}/accept
Authorization: Bearer {same approved, email-verified vendor token}
Accept: application/json
Accept-Language: en
Content-Type: application/json

{}
```

The token's vendor profile must own the `BookingVendor`, be approved, be
verified by email, and not be suspended. The booking-vendor row must still be
`pending` and its response deadline must be in the future. The route accepts an
optional valid UUID `Idempotency-Key`; the body is empty. One vendor acceptance
confirms a single-vendor booking. A booking with multiple vendors remains in
vendor review until every vendor row is accepted.

The existing Filament page is `/vendor-portal/vendor-bookings-page?statusFilter=pending`;
the decision page is `/vendor-portal/vendor-booking-decision-page?bookingVendor={bookingVendorPublicId}`.
Choose the pending booking's `vendor-portal.decision.title` decision action,
then confirm the header action labelled by `vendor-portal.decision.accept`; the
confirmation modal uses `vendor-portal.bookings.accept_confirm` and the success
notification is `vendor-portal.bookings.accepted`. The UI calls the same
`VendorAcceptBookingAction` as the API route.

For a positive non-production payment contract, `APP_ENV=testing` now binds
`PaymentGateway` to `Tests\\Fakes\\SuccessfulPaymentGateway` when the test
class is available. It returns a deterministic synthetic redirect and safe
metadata for the booking public ULID. The production provider path remains
Paymob, and the existing `Tests\\Fakes\\FakePaymentGateway` remains
fail-closed in the vendor lifecycle QA harness. No real gateway, secret,
webhook, capture, or settlement path was used. Payment initiation creates a
pending payment; ledger posting remains outside this slice.

## Changes and checks

The production payment route and action were preserved. The only runtime
binding change is an `APP_ENV=testing` branch in `PaymentsServiceProvider`; it
does not select the test double in production. The focused tests now cover
the published service page, authenticated wizard form payload, localized cart
entry, checkout review's draft boundary, payment route middleware, and the
deliberate `/en/checkout` 404, plus the vendor acceptance and positive payment
contracts. The payment provider's existing refund timeline bindings now use
their actual contract and repository namespaces, and the payment controller
narrows the authenticated user before reading identity fields. The action's
response shape is documented for static analysis.

| Check | Result |
|---|---|
| `tests/Feature/CustomerPurchaseRouteContractTest.php` | **PASS — 5 tests, 29 assertions** |
| `php vendor/bin/pint tests/Feature/CustomerPurchaseRouteContractTest.php` | **PASS** |
| `php -l tests/Feature/CustomerPurchaseRouteContractTest.php` | **PASS** |
| PHPStan on existing cart routes/controllers | **PASS — 0 errors** (previous focused run) |
| `tests/Feature/BookingItemCommercialSnapshotTest.php` filtered published-service/cart check | **PASS — 1 test** (previous focused run) |
| `tests/Feature/PaymentsIntegrityRegressionTest.php` filtered ownership/payment guards | **PASS — 2 tests, 7 assertions** (previous focused run) |
| `tests/Feature/CustomerPurchasePaymentContractTest.php` | **PASS — 3 tests, 22 assertions** (card persistence, amount/currency, one payment, and null capture-ledger link) |
| `tests/Fakes/SuccessfulPaymentGateway.php` through the testing provider binding | **PASS — deterministic initiation contract exercised** |
| `tests/Feature/VendorLifecycleQaHarnessTest.php` | **PASS — 4 tests, 19 assertions**; existing fail-closed fake remains active in the harness |
| PHPStan on changed Payment provider/action/controller files | **PASS — 0 errors** |
| Pint `--test` on changed PHP files | **PASS** |
| `tests/e2e` | **Not run**, per scope |
| Browser customer sequence through cart, review, and payment guard | **PASS — review 200/`ready_to_submit=false`; payment 422 before gateway** |
| Browser vendor acceptance and successful payment | **UNVERIFIED**; shared E2E was not changed or run |

The Playwright runtime has no official mail inbox or notification capture hook.
Vendor email verification is sent by Laravel's `VerifyEmail` notification after
commit, while the browser harness only exercises the prompt gate. The current
Playwright environment does not set a test mail transport or expose a mail
reader route, and no signed verification URL can be extracted from the browser
without an inbox integration or a state bypass. The vendor email gate therefore
remains a genuine Browser blocker; this wave did not mark users verified or add
an extraction route.

The standard sandbox Pest invocation remains blocked by the known Laravel
bootstrap realpath issue (`A facade root has not been set` / Faker
`randomElement`). The escalated focused Pest run passed.

## Coordinator fixture

Run the browser journey with isolated data and one seeded QA customer:

1. Open `/en/search`, choose a published service, and capture its public ULID.
2. Open `/en/services/{servicePublicId}` and follow its link to
   `/en/wizard?service={servicePublicId}`.
3. Complete occasion, future event dates, governorate/city, recipient address,
   and phone. Submit the CSRF form and confirm `/en/cart`.
4. Confirm the cart item and EGP minor-unit total. Before submitting, call the
   API checkout-review endpoint with the same session and assert
   `data.checks.is_draft=true` and inspect `ready_to_submit`.
5. Submit the cart form to `/en/cart/{bookingPublicId}/submit`. Expect the
   existing vendor-review workflow; do not navigate to `/en/checkout`.
6. Use the same approved and email-verified vendor session to accept
   `POST /api/v1/vendor/booking-vendors/{bookingVendorPublicId}/accept` with an
   empty JSON body. Alternatively, use the pending row's decision page and
   its `vendor-portal.decision.accept` action. Confirm that the booking is
   `confirmed` before proceeding.
7. Call the payment endpoint with `{"method":"card"}` and a stable
   `Idempotency-Key`. Under the Playwright `APP_ENV=testing` runtime the
   test-only gateway returns a deterministic synthetic redirect; repeat the
   same request to verify one payment and the cached response. The harness
   that explicitly binds `FakePaymentGateway` remains fail-closed.
8. Verify that another customer receives 404 for the booking public ULID.

## Remaining boundary

The `/en/checkout` 404 and the sequence `submit -> checkout-review -> payment`
are E2E contract mismatches with the existing code. The current application
requires review before submit, vendor acceptance before payment, and the
testing-only success binding for a positive payment initiation result. The
focused action contract is **PASS**; full customer purchase acceptance remains
**PARTIAL** until the coordinator proves the corrected Browser sequence with
isolated data. Browser vendor email verification also remains **BLOCKED** until
Playwright has an approved mail capture path; no bypass is acceptable.
