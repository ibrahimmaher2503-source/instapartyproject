# Wave 8 Purchase Chain Results

Date: 2026-09-13  
Issue: connected vendor-to-customer sale purchase journey  
Status: **PASS — focused Laravel chain**

## Verified chain

`tests/Feature/FullPurchaseJourneyTest.php` creates one approved vendor and
one approved sale product type, creates one bilingual sale service, uploads its
gallery image, submits it for review, and publishes it through the existing
catalog Actions. It then registers one customer through the storefront
register and OTP verify endpoints, selects that exact service, creates one
booking through the storefront cart setup contract, checks checkout review,
submits the booking, accepts it as the same vendor, and initiates payment as
the same customer.

The focused run passed **1 test / 44 assertions**. It proved:

- the service is published before the customer can select it;
- the wizard and cart use the service public ULID and city public ULID;
- checkout review is ready for the draft and returns `25000` minor units in
  `EGP`;
- booking lifecycle moves `draft` → `vendor_review` → `confirmed`;
- the sale reservation moves `held` → `confirmed` with quantity `1`;
- the testing-only gateway receives the real production payment action and
  returns its deterministic redirect;
- a repeated fixed `Idempotency-Key` returns the same payment public ID and
  leaves exactly one pending card Payment for `25000 EGP`;
- the booking remains `unpaid`, because this gateway contract covers payment
  initiation only and does not invent capture or settlement behavior.

## Coordinator handoff fixture

The IDs are generated per isolated test run. Pass these public values between
steps; do not substitute database integer IDs in storefront or API URLs:

```text
servicePublicId        = published sale service public ULID
occasionCode           = generated active occasion code
cityPublicId           = generated active city public ULID
bookingPublicId        = draft booking public ULID
bookingVendorPublicId  = vendor review row public ULID
```

The existing contracts use this payload and sequence:

```http
POST /api/v1/vendor/services/sale
{
  "name": {"en": "Full Journey Sale Service", "ar": "خدمة بيع رحلة كاملة"},
  "short_description": {
    "en": "A complete purchase chain fixture.",
    "ar": "بيانات اختبار لمسار شراء كامل."
  },
  "category_id": "<active-sale-category-internal-id-for-this-api-contract>",
  "base_price_minor": 25000,
  "is_perishable": false,
  "is_made_to_order": false,
  "stock_quantity": 3
}
```

After gallery upload, the vendor submits for review and the admin publishes.
For the customer, use the same published service:

```text
GET  /en/services/{servicePublicId}
GET  /en/wizard?service={servicePublicId}
POST /en/cart/setup
  service_id={servicePublicId}
  occasion={occasionCode}
  event_starts_at=<future datetime>
  event_ends_at=<after event_starts_at>
  address[city_id]={cityPublicId}
  address[address_line]=25 Full Journey Street
  address[recipient_name]=Full Journey Customer
  address[recipient_phone_e164]=<customer phone>
  quantity=1
POST /en/cart/{bookingPublicId}/submit
POST /api/v1/customer/bookings/{bookingPublicId}/checkout-review  body={}
POST /api/v1/vendor/booking-vendors/{bookingVendorPublicId}/accept body={}
POST /api/v1/customer/bookings/{bookingPublicId}/payments
  Idempotency-Key: full-purchase-payment-0001
  {"method":"card"}
```

The vendor acceptance requires the same approved, email-verified vendor and
the row must still be pending. Payment requires the same customer, confirmed
booking, `method=card`, and the stable idempotency key. The testing binding is
`Tests\\Fakes\\SuccessfulPaymentGateway`; production gateway selection is
unchanged.

## Checks

| Check | Result |
|---|---|
| `php vendor/bin/pest tests/Feature/FullPurchaseJourneyTest.php --bail` | **PASS — 1 test, 44 assertions** |
| `php vendor/bin/pint tests/Feature/FullPurchaseJourneyTest.php` | **PASS — formatted the test** |
| `php vendor/bin/phpstan analyse tests/Feature/FullPurchaseJourneyTest.php --no-progress --memory-limit=512M` | **24 test-file baseline errors**; PHPStan is configured for `app` and `database`, and the errors are Pest `$this` magic plus Eloquent dynamic test properties |
| `tests/e2e` / Browser | **Not run** for this backend harness task |

No production gateway, secrets, schema, shared E2E, or ledger file was
changed. External mail extraction, real browser acceptance, capture, webhook,
and settlement remain outside this focused contract and are unverified.
