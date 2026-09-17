# Wave 6 — Subscription integrity

Date: 2026-09-13

Scope: `BUG-SUBSCRIPTION-INTEGRITY-001` only. No production data, `.env`,
migrations, packages, ledger or shared e2e files were changed. Git was not
repaired; `git status` is unavailable in this checkout.

## Findings and fixes

- **CLOSED — stale effective reads.** `currentForVendor()` and
  `currentPaidForVendor()` now use `VendorSubscription::effectiveAt()`. Active
  rows past `current_period_end`, past-due rows past their grace window, and
  active admin overrides past `override_expires_at` are excluded from current
  gating/API reads while historical rows remain visible to admin lists.
- **CLOSED — auto-enrol race in the application path.** The free-tier action
  locks the existing `vendor_profiles` row inside its transaction and repeats
  the effective-subscription lookup before inserting. Sequential idempotency
  is covered; a real concurrent database run remains unverified.
- **CLOSED — lifecycle transition recursion.** Subscription state handlers were
  calling `transitionTo()` from inside `handle()`, which recursed until the
  PHP process exhausted 2 GB during the expiry test. The five handlers now set
  the target state once, matching the working state-transition pattern already
  used in Booking. Expiry still writes the audited `grace_expired` reason.
- **CLOSED — admin relationship and payment presentation defects.** Admin
  resources and the repository now use the model's `vendor()` relationship;
  the resource no longer reads phantom `due_date` or payment `amount_minor`
  columns. Payment amount is displayed through `invoice.amount_minor`.
- **CLOSED — development fixture linkage.** The development seeder now adds an
  insert-only captured `SubscriptionPayment` for each seeded paid invoice,
  using the invoice gateway reference. This makes the local Payments page
  explain the seeded paid invoice.
- **CLOSED — provider boot defect.** The provider referenced an absent
  `OnSubscriptionExpired` listener class. The stale registration was removed;
  no expiration scheduler or listener implementation was invented.

## Verification

- Focused Pest outside the sandbox: **PASS — 4 tests, 10 assertions, 3.59s**
  (`tests/Feature/SubscriptionIntegrityTest.php`).
- Targeted Pint: **PASS** on all changed application, resource, transition,
  seeder and focused-test files.
- PHP syntax checks: **PASS** for every changed PHP file.
- Unprivileged Pest: **BLOCKED** before Laravel bootstrap because the isolated
  process reports `A facade root has not been set`; the project path is not
  visible to that process. One escalated PHPStan attempt was interrupted before
  producing a result, so no PHPStan pass is claimed.
- Browser acceptance: **UNVERIFIED** in this wave.

## Remaining contract gaps

- **PARTIAL — renewal/payment lifecycle.** This checkout has no subscription
  invoice creation or renewal action, no subscription cancellation route, no
  subscription scheduler/queue expiry command, and `OnPaymentCaptured` is an
  explicit usage-metering stub. The invoice schema has no `due_at`/`due_date`
  column, and no approved billing policy was available to invent one.
- **PARTIAL — existing paid records.** The local development seed is linked,
  but no production/legacy inventory or remediation was run. Existing paid
  invoices without a payment, credit, or manual-settlement reference need an
  approved read-only inventory and reversible operational decision.
- **PARTIAL — concurrency.** The parent-row lock closes the application race
  path, but the focused test is sequential and does not claim two-process
  contention evidence.

## Browser fixture for coordinator

Use isolated data with one vendor profile (non-empty EN/AR business name), one
expired active paid subscription, one expired active admin override, one paid
invoice with period dates and a matching captured subscription payment. The
expected Browser checks are: expired rows remain visible in the admin list,
neither expired row is returned as the vendor's current subscription, the
vendor name renders, the invoice amount renders, and the payment row links to
the invoice reference. No production or shared database mutation is required.
