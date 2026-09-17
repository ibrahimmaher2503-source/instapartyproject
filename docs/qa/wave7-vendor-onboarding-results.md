# Wave 7 — Vendor onboarding results

Date: 2026-09-13  
Scope: `BLOCK-VENDOR-E2E-001` and the missing vendor phone-verification UI only.

## Finding

The vendor panel's email gate was working as designed: a newly registered vendor was redirected to Filament's email verification prompt, and the documents and service routes were consequently redirected to that prompt. The existing approval contract also requires both `email_verified_at` and `phone_verified_at` in its `contact` check.

The blocking defects were that the vendor panel had no phone-verification page or vendor route, and vendor registration used Laravel's generic `VerifyEmail` notification. That notification attempted to generate `verification.verify`, which is not registered in this application, so registration could fail before a mail URL existed. The approval gate therefore remained incomplete before and after email verification.

## Change

Added `VendorPhoneVerificationPage`, discovered by the normal vendor panel at:

`/vendor-portal/vendor-phone-verification`

The page:

- is available only to an authenticated user with the vendor role and a vendor profile;
- remains behind the existing email-verification middleware;
- displays the authenticated vendor's registered phone as disabled/read-only;
- sends the code through `SendOtpAction` (including its existing rate limiter and gateway binding);
- verifies the code through `VerifyPhoneAction`, which owns the transaction and `PhoneVerified` event;
- provides English and Arabic labels and notifications;
- does not change approval eligibility, product-type gates, persistence schema, or external transports.

Vendor registration now uses Filament's existing vendor email notification and route (`filament.vendor.auth.email-verification.verify`). That route already applies the vendor panel guard, Laravel's standard signed URL and six-per-minute throttle, and `EmailVerificationRequest` ownership/hash checks. Its normal response redirects back into the vendor portal.

## Focused verification

`php vendor/bin/pest tests/Feature/VendorPhoneVerificationPageTest.php --bail`

Result: **PASS — 4 tests, 16 assertions**.

The tests cover the existing email redirect, an HTTP route plus Livewire send/verify flow, denial for a customer or a profile-less vendor identity, and reachability of the existing profile/documents/hours/coverage pages after email verification. PHP syntax checks passed for the new PHP page, tests, listener, and both edited translation files. Targeted Pint passed after formatting.

The configured PHPStan run was attempted with the new page, but `phpstan.neon` excludes `app/Modules/*/Filament/**/*`, so PHPStan returned `No files found to analyse`. This is recorded as **BLOCKED_BY_CONFIG**, not a pass claim. The two existing supporting actions used by the page were analysed separately and passed PHPStan with no errors.

Email verification checks:

`php vendor/bin/pest tests/Feature/VendorEmailVerificationTest.php --bail`

Result: **PASS — 4 tests, 13 assertions** (route registration, signed notification URL, same-vendor verification and portal redirect, invalid signature/hash/foreign-vendor rejection).

`php vendor/bin/pest tests/Feature/VendorRegistrationLifecycleTest.php --bail`

Result: **PASS — 10 tests, 59 assertions**. The listener's retry/backoff contract and existing registration journeys remain green. Targeted PHPStan for `SendVendorRegistrationEmailVerification` passed with no errors.

## Browser handoff steps

Use an isolated local/testing database and the normal (non-minimal) vendor panel:

1. Register a new vendor at `/vendor-portal/register` with a unique email, an E.164 phone, bilingual business name, business type, governorate, and city.
2. Confirm the vendor reaches `/vendor-portal/email-verification/prompt`. Read the verification URL from the local test mail capture and follow that URL in the same browser session. Do not mark email verification complete from a database edit.
3. After the email link redirects back to the intended vendor page, open `/vendor-portal/vendor-phone-verification`. Click **Send verification code**, then enter the code delivered by the configured local test gateway (`000000` for the local stub) and click **Verify phone number**.
4. Complete the existing vendor profile UI, including both translations, valid geography, business identity fields, and the banking fields. Masked or empty IBAN values remain saveable without being treated as a new IBAN.
5. Upload each required document through `/vendor-portal/vendor-documents` and use the admin review UI to approve the documents. Add at least one business-hours entry and one coverage area through their existing vendor pages.
6. In the admin approval queue, confirm the existing eligibility checklist shows contact, profile, banking, documents, hours, and coverage complete before approving the vendor. Product-type approval remains an admin decision; create a sale service only after the sale type is approved.
7. Confirm a guest, customer, or vendor identity without a vendor profile cannot open `/vendor-portal/vendor-phone-verification`.

## Status and limits

**PARTIAL.** The missing vendor email notification contract and phone-verification route/action/view defects are fixed and covered by focused Feature tests. Actual headed Browser execution was not run in this slice, so the full E2E journey, captured mail interaction, seeded geography fixture, document review, and final admin approval remain Browser handoff work. No `tests/e2e` or ledger files were changed.

## Onboarding handoff supplement

The current profile-to-approval route is executable through the normal vendor
panel after email and phone verification:

1. **Profile:** `/vendor-portal/vendor-profile-page` (`filament.vendor.pages.vendor-profile-page`). Save both `business_name_en` and `business_name_ar`, `address_line_en` and `address_line_ar`, `primary_governorate_id`, and its matching `primary_city_id`. The profile page now also exposes the required business identity field for the selected type: `national_id` for an individual; `commercial_register_no` for an establishment; and both `commercial_register_no` and `tax_id` for a company. Banking fields are `bank_name`, `bank_account_holder`, and `bank_iban` (SWIFT is optional for eligibility). The save control is the page form's **Save** action.
2. **Documents:** `/vendor-portal/vendor-documents-page` (`filament.vendor.pages.vendor-documents-page`). Use the header **Upload** action, choose `doc_type`, and attach one PDF/JPEG/PNG file up to 10 MB. Required types are individual: `national_id` + `iban_proof`; company: `cr` + `tax_card` + `iban_proof`; establishment: `cr` + `tax_card` + `national_id` + `iban_proof`. The uploaded state is **Pending**; admin must review and approve the latest required document of each type. A pending duplicate type is blocked; a rejected row exposes **Re-upload** and **Delete**.
3. **Business hours:** `/vendor-portal/vendor-business-hours-page` (`filament.vendor.pages.vendor-business-hours-page`). Each day has an **Open** toggle. For an open day, `opens_at` and `closes_at` are visible with defaults `09:00` and `22:00`; closed days persist null times. Click **Save**. Eligibility only needs at least one persisted business-hours row.
4. **Coverage:** `/vendor-portal/vendor-coverage-areas-page` (`filament.vendor.pages.vendor-coverage-areas-page`). Use **Add area**, select an active city, then enter `delivery_fee` and `min_order` in EGP (both default to zero and accept zero or greater). The table exposes **Edit** and **Remove**. Eligibility only needs at least one persisted coverage row.
5. **Admin approval:** `/admin/vendor-approval-queue` (`vendor-approval-queue` resource). Open a pending row through **Review**, then inspect the **Approval requirements** checklist. The profile action **Approve profile** is visible only for a pending row and an admin with `approve_vendor_profile`; it remains disabled until identity, contact verification, bilingual profile/geography, banking, hours, coverage, and latest required documents are complete. Confirming it runs `ApproveVendorProfileAction` and moves the profile to **Approved**. Product-type approval remains a separate admin action: **Approve for rental**, **Approve for sale**, or **Approve for digital**, visible after overall approval and `approve_vendor_for_type` permission.

The matching API contracts are already present for app clients: `PUT
/api/v1/vendor/profile`; `GET|POST /api/v1/vendor/documents`;
`GET|PUT /api/v1/vendor/business-hours`; `GET|POST
/api/v1/vendor/coverage-areas`; and admin `GET
/api/v1/admin/vendor-profiles` plus `POST
/api/v1/admin/vendor-profiles/{vendorProfile}/approve`. Document upload requires
multipart `doc_type` and `file`; hours use the day rows; coverage uses city and
integer minor-unit fee/order fields. These routes require the existing Sanctum
role guards; admin approval also requires the existing approval permission.

The confirmed root defect was that `VendorProfilePage` and
`UpdateVendorProfileRequest` exposed no way to provide the identity columns
used by `VendorApprovalEligibilityService`, so a newly registered vendor could
never satisfy the profile check through the supported UI/API. The profile page
now exposes those type-specific fields and the request accepts them. The same
page also previously rejected an empty or masked existing IBAN during a normal
save even though the save action intentionally preserved masked values; that
validation path now accepts blank or the current mask while retaining real IBAN
validation. Focused coverage is in `tests/Feature/VendorProfileOnboardingUiTest.php`:
**2 tests, 10 assertions PASS**. Pint `--test`, PHP syntax, and PHPStan on the
changed profile page/request all pass with zero errors. Headed Browser execution
and document admin approval remain **UNVERIFIED** in this slice.
