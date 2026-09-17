# Wave 8 Full Marketplace Chain Handoff

Date: 2026-09-14  
Status: **PASS — connected Feature contract**

## Evidence

`tests/Feature/FullMarketplaceJourneyTest.php` runs one connected journey with
the same vendor, profile, service, booking, and customer. The last verified
run passed **1 test / 86 assertions**. It does not seed an approved vendor or
approved service, assign approval states directly, write a booking/payment
record directly, or replace the real subscription policy with an allow stub.

The verified chain is:

```text
vendor registration
  -> signed vendor email verification
  -> vendor phone OTP
  -> bilingual profile and banking
  -> document upload
  -> admin document approval
  -> business hours + coverage
  -> admin vendor profile approval
  -> admin sale-type approval
  -> same vendor creates and publishes bilingual sale service
  -> customer registration + OTP
  -> same service wizard/cart/review/submit
  -> same vendor accepts booking
  -> same customer starts card payment with idempotency
```

## Vendor fixture and labels

Generate one governorate and city for the run. Register through the existing
API contract:

```http
POST /api/v1/register/vendor
{
  "name": "Marketplace Vendor",
  "email": "<unique vendor email>",
  "phone_e164": "<unique E.164 phone>",
  "password": "SafePassword123!",
  "password_confirmation": "SafePassword123!",
  "business_name": {
    "en": "Marketplace Events",
    "ar": "فعاليات السوق"
  },
  "business_type": "individual",
  "primary_governorate_id": "<generated internal governorate id>",
  "primary_city_id": "<generated internal city id>",
  "preferred_locale": "en"
}
```

The registration response exposes the vendor ULID as `data.id`. The test
links it to the persisted profile, then obtains the official signed URL from
the vendor Filament panel (`Filament::getPanel('vendor')->getVerifyEmailUrl`).
The vendor phone page uses the existing `VendorPhoneVerificationPage` labels
and `sendCode` / `verifyPhone` actions with the test OTP `123456`.

The profile form uses these existing field names:

```text
business_name_en, business_name_ar
address_line_en, address_line_ar
national_id
primary_governorate_id, primary_city_id
bank_name, bank_account_holder, bank_iban, bank_swift
```

The vendor documents page exposes the existing `upload` action. The test
uploads `national_id` and `iban_proof` through `UploadVendorDocumentAction`,
then the admin uses `ApproveVendorDocumentAction` with
`review_vendor_documents`. Business hours use the existing
`VendorBusinessHoursPage` save action; coverage uses
`VendorCoverageAreasPage` action `addArea`. The admin then runs
`ApproveVendorProfileAction` with `approve_vendor_profile` and
`ApproveVendorForTypeAction` with `approve_vendor_for_type` for `sale`.

## Service creation and publication

The same approved vendor creates the only service in the journey through
`CreateSaleServiceAction` and `CreateSaleServiceDTO`:

```text
vendorProfileId = <same approved vendor profile internal id>
categoryId      = <generated category allowing sale>
name.en         = Marketplace Sale Service
name.ar         = خدمة بيع السوق
shortDescription.en = Connected sale fixture.
shortDescription.ar = بيانات بيع مترابطة.
basePriceMinor  = 25000
isPerishable    = false
isMadeToOrder   = false
leadTimeHours   = null
stockQuantity   = 3
customizationFields = null
```

The test adds one gallery image, submits with
`SubmitServiceForReviewAction`, and publishes with `PublishServiceAction` as
the admin holding `publish_sale_service`. The resulting service public ULID
is the only `service_id` used by the customer journey.

## Customer and booking handoff

Register and verify the customer through the existing API contracts:

```http
POST /api/v1/register/customer
{
  "name": "Marketplace Customer",
  "phone_e164": "<unique E.164 phone>",
  "email": "<unique customer email>",
  "password": "CustomerUat123!",
  "password_confirmation": "CustomerUat123!",
  "preferred_locale": "en",
  "accepted_terms": "1"
}

POST /api/v1/phone/otp/send
{"phone_e164":"<customer phone>"}

POST /api/v1/phone/verify
{"phone_e164":"<customer phone>","code":"123456"}
```

Use the resulting customer identity and the published service public ULID:

```text
GET  /en/services/{servicePublicId}
GET  /en/wizard?service={servicePublicId}
POST /en/cart/setup
  service_id={servicePublicId}
  occasion={active occasion code}
  event_starts_at=<future datetime>
  event_ends_at=<later datetime>
  guest_count=2
  celebrant_name=Marketplace Child
  address[city_id]={same city public ULID}
  address[address_line]=25 Market Street
  address[recipient_name]=Marketplace Customer
  address[recipient_phone_e164]={customer phone}
  quantity=1
```

The booking public ULID is then used for:

```http
POST /api/v1/customer/bookings/{bookingPublicId}/checkout-review
{}

POST /en/cart/{bookingPublicId}/submit
{}
```

Review returns `ready_to_submit=true`, `total_minor=25000`, and `currency=EGP`.
The booking moves from `draft` to `vendor_review` and its item points to the
same published service. The linked `bookingVendorPublicId` is then accepted
by that same vendor:

```http
POST /api/v1/vendor/booking-vendors/{bookingVendorPublicId}/accept
{}
```

The booking becomes `confirmed` and its inventory reservation becomes
`confirmed` with quantity `1`.

## Payment handoff and boundary

The same customer initiates the existing payment action:

```http
POST /api/v1/customer/bookings/{bookingPublicId}/payments
Accept: application/json
Accept-Language: en
Idempotency-Key: marketplace-payment-0001

{"method":"card"}
```

`APP_ENV=testing` uses `Tests\\Fakes\\SuccessfulPaymentGateway`. The test
proves one Payment only, card method, `25000` minor units, `EGP`, deterministic
`test-{bookingPublicId}` gateway reference, and the same payment public ULID
on a repeated idempotent request. The payment remains `pending` and the
booking remains `unpaid`; this gateway/action contract only initiates payment.
Capture, webhook, settlement, and a `paid` state are outside this slice and
remain unverified.

## Scope

No production gateway, secrets, schema, shared E2E, or ledger file was
changed. Browser/full-suite acceptance was not claimed from this Feature
test. All IDs passed between records in the test are public ULIDs at API and
storefront boundaries; internal IDs are used only where the existing creation
Action requires them.

## Custom Chromium purchase-chain follow-up — 2026-09-15

- Custom Chromium: **PASS — 2 tests in 2.7 minutes**. The same vendor, service, and customer were used through the tested path; the vendor documents and service gallery for `01M2GZJYW7YA6QDS50Y5EZ7MF3` were uploaded through the UI, then admin published the service.
- Booking `01M2GZM23V4FFHA86QV2Z0CMCM` was submitted, accepted by the same vendor, and reached `confirmed`.
- Payment `01M2GZM5043594KGGK6SAVP49K` returned HTTP 201 with state `pending`; the booking remained `unpaid`.
- Still open: capture/webhook/paid state, the reported Edit 403, and multi-party settlement/withdrawal/reconciliation. Full suites are pending; the focused Browser run does not establish them.

## Notification listener follow-up — 2026-09-15

`OnServicePublished` and `OnServiceRejected` previously referenced an
undefined `vendorProfile` when resolving the notification recipient. Both now
resolve the user through the service's `vendor.user` relation and safely skip
when no owner user exists. Focused Pest passed **5 tests / 17 assertions**;
targeted Pint and PHPStan passed. Full Pest passed **177 tests / 1,701
assertions**. Full Playwright remains partial; see the 2026-09-15 diagnosis in
the full marketplace Browser results. This listener evidence does not extend
the purchase-chain claims above.
