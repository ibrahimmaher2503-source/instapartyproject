# Full marketplace Browser E2E results

Date: 2026-09-15  
Environment: local Laravel server at `http://127.0.0.1:8139`, Chromium, one
worker, isolated `storage/e2e.sqlite` created by Playwright setup. No
production data, `.env` changes, real gateway, or direct database approval
mutation was used.

## Current evidence

The stage table and detailed run logs below are the historical 2026-09-13
snapshot. The 2026-09-15 Browser follow-up at the end supersedes it for the
stages explicitly covered there.

The latest custom Chromium run on 2026-09-15 passed **2/2 tests in 2.7
minutes**. It exercised the same vendor, service, and customer through the
vendor documents/gallery flow and admin publication, then submitted and
accepted the booking and initiated payment. Exact public IDs and the payment
boundary are recorded in the 2026-09-15 addenda below. This run supersedes the
older Browser blockers below only for the stages it reached; capture/webhook/
paid, Edit 403, multi-party settlement/withdrawal/reconciliation, and full
suite validation remain open or pending.

The journey is defined in
`tests/e2e/full-marketplace-journey.spec.ts`. The last complete custom run
before the profile completion edit passed **2/2 tests** (setup plus journey).
The subsequent focused run passed **1/1 Browser test** while inspecting the
profile controls. After the artifact-backed Arabic-tab selector was applied,
the latest one-time rerun passed setup, profile save, and reached the new
document workflow, then failed at the first upload gate: **1 passed, 1 failed
in 38.2s**. The current spec fills the visible `national_id` field for the
individual vendor fixture.

| Stage | Result | Browser evidence |
|---|---|---|
| New vendor registration | **PASS** | A unique vendor was submitted at `/vendor-portal/register` and reached `/vendor-portal/email-verification/prompt`. The isolated fixture now seeds the Free plan required by the registration listener. |
| Vendor email verification | **PASS** | The URL was captured from the local mail log and opened in the same vendor Browser context through `/vendor-portal/email-verification/verify/{id}/{hash}`. |
| Vendor phone verification | **PASS** | The same context opened `/vendor-portal/vendor-phone-verification-page`, sent the local testing code, entered `000000`, and displayed the verified state. |
| Vendor profile and banking | **PASS in latest custom run** | `/vendor-portal/vendor-profile-page` opened the artifact-backed Arabic tab, filled both translations, `national_id`, geography, and banking, saved, and retained the English business name. |
| Vendor documents | **BLOCKED_BY_E2E_SELECTOR** | `/vendor-portal/vendor-documents-page` was reached, but the upload step resolved five hidden Filament `role=dialog` nodes instead of the active form dialog; no document was uploaded or approved. |
| Business hours and coverage | **UNVERIFIED** | The real vendor pages were not reached after the document selector blocker. |
| Admin approval | **UNVERIFIED** | Document approval and eligibility review were not reached in the latest run. |
| Same-vendor sale service | **BLOCKED** | `/vendor-portal/vendor-sale-services/create` returned 403 for the unapproved vendor. No service was created. |
| New customer registration and OTP | **PASS** | The storefront form at `/en/auth/register` reached `/en/auth/verify`; the local six-digit code completed the same customer identity. |
| Seeded service browse/cart/submit | **PARTIAL, latest run BLOCKED** | Earlier diagnostic evidence reached `/en/services/{publicId}` → `/en/wizard?service={publicId}` → `/en/cart` and the API review boundary. The latest run stopped in the wizard because the Cairo option was absent from `[data-planner-governorate]`; the isolated preseeded service remains diagnostic only. |
| Checkout review | **PASS at contract boundary** | Same customer API context posted `/api/v1/customer/bookings/{bookingPublicId}/checkout-review` before payment and received 200 with `ready_to_submit=false` because the booking remained in vendor review. |
| Payment/purchase completion | **BLOCKED** | The same customer posted `/api/v1/customer/bookings/{bookingPublicId}/payments` with `method=card` and a fixed idempotency key; the expected guard response was 422. A successful purchase, gateway reference, ledger entry, and role dashboard effect were not produced. |

## Exact Browser output

The custom run recorded:

```text
VENDOR_EMAIL_CAPTURE_RESULT true
VENDOR_DOCUMENTS_RESULT 200 /vendor-portal/vendor-documents-page
VENDOR_SERVICE_RESULT 403 /vendor-portal/vendor-sale-services/create
CHECKOUT_REVIEW_RESULT 200 false
PAYMENT_ATTEMPT_RESULT 422
```

The latest Browser output was:

```text
1 passed (setup)
1 failed (journey) — tests/e2e/full-marketplace-journey.spec.ts:108
Test timeout of 30000ms exceeded
locator('button').filter({ hasText: 'العربية' }).first() matched a hidden
global locale-menu item
```

After scoping the locator to the handed-off selector
`form #translations` and requiring an exact `role=tab` named `العربية`, the
single allowed rerun still reached the profile page but found no matching tab
and timed out at line 108 after 30 seconds. Its final result was **1 passed
(setup), 1 failed (journey), 55.6s**. No further Browser retry was made.

The next single rerun used the artifact-backed tablist selector and passed the
profile save step. It reached customer wizard location selection and failed
because no Cairo option was attached:

```text
1 passed (setup)
1 failed (journey) — tests/e2e/full-marketplace-journey.spec.ts:242
Test timeout of 30000ms exceeded
locator('[data-planner-governorate]').locator('option').filter({ hasText: /Cairo|القاهرة/i }).first()
```

Its final result was **1 passed (setup), 1 failed (journey), 56.8s**. No
further Browser retry was made.

The latest one-time clean rerun expanded the vendor onboarding flow. It passed
the profile save step, reached `/vendor-portal/vendor-documents-page`, and
failed before the first file selection because the unscoped
`getByRole('dialog')` matched five Filament dialog nodes, including hidden
action/table/bulk/infolist/form dialogs:

```text
1 passed (setup)
1 failed (journey) — tests/e2e/full-marketplace-journey.spec.ts:146
Strict mode violation: getByRole('dialog') resolved to 5 elements
No document was uploaded
Final result: 1 passed (setup), 1 failed (journey), 38.2s
```

No further Browser retry was made. The document workflow must scope the active
visible form-action dialog before hours, coverage, document approval, overall
approval, sale type approval, service publication, and purchase can be tested.

The earlier statement above reflects the 2026-09-13 run only and is
superseded for document upload, service publication, vendor acceptance, and
payment initiation by the 2026-09-15 evidence below. Hours/coverage and admin
approval substeps are not asserted by that newer result unless separately
recorded in its Browser evidence.

## Latest custom Chromium evidence — 2026-09-15

Custom Chromium: **PASS — 2 tests in 2.7 minutes**. Through the UI, the same
vendor uploaded the required documents and service gallery for service
`01M2GZJYW7YA6QDS50Y5EZ7MF3`; the admin published the service. The same
customer selected that service and submitted booking
`01M2GZM23V4FFHA86QV2Z0CMCM`, and the same vendor accepted it; the booking
reached `confirmed`. Payment `01M2GZM5043594KGGK6SAVP49K` returned HTTP 201
and remained `pending`; the booking remained `unpaid`.

Still open: payment capture/webhook and a paid state; the reported Edit 403;
multi-party settlement, withdrawal, and reconciliation. Full suites are
pending. This Browser result does not claim those stages or broader suite
coverage.

## Earlier boundaries and remaining work — 2026-09-13 snapshot

The approval service requires verified contact, complete bilingual profile and
banking, valid geography, approved required documents, hours, and coverage.
The 2026-09-13 Browser run reached the actual disabled approval action and
preserved those requirements. Its missing-Cairo blocker applied to that run;
the 2026-09-15 follow-up later exercised a same-vendor/customer booking and
payment-initiation path as recorded above.

The shared E2E fixture change is limited to seeding the existing Free plan in
the isolated database. The custom Browser journey uses the existing routes and
does not add `/checkout`, alter schema, bypass approval, or change production
gateway behavior. The remediation ledger has a separate 2026-09-15 evidence
addendum; its existing open items remain open.

## Playwright follow-up diagnosis — 2026-09-15

The latest attempted journey remains **PARTIAL: 1 setup passed, 1 journey
failed**. The approval flow failed at its second vendor-document action: the
test searched an ancestor `[role=dialog]` that was absent, while the visible
`.fi-modal-window` contained the expected prompt and Confirm button. The
`mountTableAction` request returned HTTP 200 without errors. This points to a
test selector/Filament modal mismatch; the action itself was not shown to have
failed. No full rerun followed this diagnosis, so no full Playwright pass is
claimed.
