# InstaParty vendor-to-settlement audit — 2026-09-12

## Decision

**Status: LOCAL CODE REMEDIATIONS HAVE FOCUSED COVERAGE; FULL MULTI-ACTOR E2E REMAINS PARTIAL.** The current Browser evidence proves one same-vendor/customer chain through service publication, booking acceptance, and payment initiation returning HTTP 201 with `pending` while the booking remains `unpaid`. Capture/webhook/paid, payout, withdrawal, reconciliation, and the complete supplier-to-settlement journey remain open or unverified.

The original audit was read-only and used three independent Luna/xhigh reviewers for catalog/security, booking/state, and payments/settlement. The remediation was then implemented locally only. Nothing was deployed or pushed.

## Remediation result

| Finding | Status | Resolution |
|---|---|---|
| VTSE-001 | FOCUSED COVERAGE | Vendor ownership now fails with a non-enumerating 404 and resubmission accepts only requested safe fields. Full multi-role Browser proof remains open. |
| VTSE-002 | FOCUSED COVERAGE | Payment reads are owner-scoped and expose the booking public ULID only. Cross-role Browser proof remains open. |
| VTSE-003 | FOCUSED COVERAGE | Booking items snapshot the resolved commission rate instead of zero. Capture and ledger proof remain open. |
| VTSE-004 | FOCUSED COVERAGE | Refund allocation is proportional, capped, retry-safe, and has one reversal event path. End-to-end refund proof remains open. |
| VTSE-005 | FOCUSED COVERAGE | Completed refunds return idempotently without another gateway call. Gateway retry proof remains open. |
| VTSE-006 | PROVEN THROUGH INITIATION | Payment initiation validates owner, booking state, items, amount, and prior payable attempts; the current Browser chain returned HTTP 201 `pending` and left the booking `unpaid`. |
| VTSE-007 | OPEN/UNVERIFIED | Capture fails closed when webhook amount or currency differs from the payment; no Browser capture/webhook proof exists. |
| VTSE-008 | OPEN/UNVERIFIED | Duplicate capture delivery rebuilds the paid projection from persisted payments; paid-state and duplicate webhook proof remain open. |
| VTSE-009 | OPEN/UNVERIFIED | Payout, withdrawal, and reconciliation remain unverified; no supplier-to-settlement journey is proven. |
| VTSE-010–VTSE-015 | FOCUSED COVERAGE | Modification expiry, lifecycle guards, trusted item data, inventory release, and cancellation/vendor decision rules have focused coverage; full journey proof remains open. |
| VTSE-016–VTSE-024 | FOCUSED COVERAGE | Focused regressions cover stock, type-aware categories, fulfillment, idempotency, ledger replay, modification decisions, webhook failure codes, and vendor filtering; end-to-end multi-party proof remains open. |
| VTSE-025 | OPEN/UNVERIFIED | The unbacked settlement-run screen is hidden, but withdrawal and reconciliation operations remain unverified in a complete supplier-to-settlement flow. |
| VTSE-D01 | RESOLVED | Suspended customers may read their history but cannot mutate bookings, payments, or reviews. Settlement customer routes are read-only. |
| VTSE-D02 | RESOLVED | Customer payment endpoints now require the customer role in addition to ownership checks. |

### Verification after remediation

- Focused regressions: **23 passed, 81 assertions**.
- Full Pest suite: **historical evidence only — 81 passed, 1028 assertions**; it is not a current full-suite claim.
- Pint on the changed PHP files: **passed**.
- Targeted PHPStan on the changed application files: **passed with no errors**. Repo-wide PHPStan is **not green** and has existing 1,000+ findings.
- Chromium admin acceptance: **3 passed** after replacing the stale settlement-run checkpoint with the real reconciliation-run screen.
- Git metadata is unavailable in this checkout (`fatal: not a git repository: (NULL)`), so no commit or diff claim is made.

### Acceptance work still open

The following verification gaps remain open and must not be described as proven:

1. One continuous customer/vendor/admin/finance/support journey across service creation, booking negotiation, payment, fulfillment, refund, withdrawal, and reconciliation.
2. Real multi-role Browser/UAT for vendor and customer screens; the current Playwright suite authenticates an admin only.
3. True concurrent-process acceptance for payment capture, refund, modification decisions, ledger posting, and withdrawal settlement.

## Original verified baseline

- Full Pest baseline before this audit: **58 passed, 947 assertions**.
- Chromium admin acceptance: **3 passed**, using an isolated SQLite database and dedicated port `8139`.
- Before remediation, the browser suite covered admin create screens, payments, booking modifications, the stale settlement-run listing, Arabic RTL, and mobile layout.
- [VendorServiceLifecycleQaTest](../../tests/Feature/VendorServiceLifecycleQaTest.php#L61) invokes service Actions directly for rental, sale, and digital. It does not prove HTTP middleware, route-model ownership, customer booking, payment, fulfillment, or settlement.
- [admin-audit.spec.ts](../../tests/e2e/admin-audit.spec.ts#L3) authenticates one admin identity. It is not a multi-role journey.

## Original severity summary

| Severity | Count | Meaning |
|---|---:|---|
| P0 | 1 | Cross-vendor write vulnerability; fix before any external acceptance. |
| P1 | 18 | Security, money, inventory, or state-integrity defect with a concrete trigger. |
| P2 | 6 | Idempotency, auditability, or API consistency defect. |
| Needs decision | 2 | Static evidence exists, but intended product policy is missing. |
| Verification gaps | 5 | Required proof is absent; these are not counted as product bugs. |

## Original confirmed findings (preserved for traceability)

### VTSE-001 — P0 — Cross-vendor service resubmission and unrestricted mass assignment

**Trigger:** an approved vendor submits `POST /api/v1/vendor/services/{service:public_id}/resubmit` for another vendor's service that has an open change request.

**Evidence:**

- [ServiceResubmitFormRequest.php](../../app/Modules/Catalog/Http/Requests/ServiceResubmitFormRequest.php#L11) authorizes every request and accepts arbitrary `changed_fields`.
- [ServiceResubmitController.php](../../app/Modules/Catalog/Http/Controllers/ServiceResubmitController.php#L20) passes the route-bound service and payload directly to the Action.
- [ServiceResubmitAfterChangesAction.php](../../app/Modules/Catalog/Application/Actions/ServiceResubmitAfterChangesAction.php#L26) does not compare the service owner with the authenticated vendor; line 45 updates the model with the submitted map.
- [Service.php](../../app/Modules/Catalog/Domain/Models/Service.php#L79) makes ownership, category, product type, price, currency, and other fields fillable.

**Impact:** a vendor can modify another vendor's service and can change ownership, category, product type, price, translations, or other fillable values. This combines IDOR with mass assignment.

**Required regression:** two vendors and one foreign service; the attacker must receive a non-enumerating 404, and submitted non-allowlisted fields must never change.

### VTSE-002 — P1 — Customer payment read IDOR and internal identifier exposure

**Trigger:** any authenticated account requests a known payment ULID belonging to another customer.

**Evidence:** [Payments customer routes](../../app/Modules/Payments/Routes/customer.php#L13) require authentication only; [ShowPaymentController.php](../../app/Modules/Payments/Http/Controllers/Customer/ShowPaymentController.php#L16) queries by `public_id` without owner scope; [PaymentResource.php](../../app/Modules/Payments/Http/Resources/PaymentResource.php#L23) returns internal `booking_id`.

**Impact:** cross-customer payment status and amount disclosure, plus violation of the public-ULID API rule.

**Required regression:** customer B must get 404 for customer A's payment; vendor/admin behavior must be explicit; the response must use the booking public ID.

### VTSE-003 — P1 — API booking items snapshot zero commission

**Trigger:** a customer adds a published service through the booking API, then payment is captured.

**Evidence:** [AddItemToBookingAction.php](../../app/Modules/Booking/Application/Actions/AddItemToBookingAction.php#L94) writes `commission_bps` as `0`; [CalculateCommissionAction.php](../../app/Modules/Settlement/Application/Actions/CalculateCommissionAction.php#L44) treats any non-null snapshot, including zero, as authoritative and skips the configured resolver.

**Impact:** the platform commission can become zero and the entire captured amount can accrue to the vendor. Category-specific commission resolution is also not proven through this path.

**Required regression:** create an API booking under a known 1500 bps rate, capture payment, and assert the persisted snapshot, commission, vendor share, and balanced ledger.

### VTSE-004 — P1 — Multi-item refund reverses excessive commission and has two reversal paths

**Trigger:** a full or partial payment refund where the payment covers multiple booking items.

**Evidence:** [ProcessRefundAction.php](../../app/Modules/Payments/Application/Actions/ProcessRefundAction.php#L93) directly reverses the first commission using the complete refund amount, then emits `RefundCompleted`; [ReverseCommissionOnRefundCompletedListener.php](../../app/Modules/Settlement/Application/Listeners/ReverseCommissionOnRefundCompletedListener.php#L80) applies the same full amount to every commission; [ReverseCommissionAction.php](../../app/Modules/Settlement/Application/Actions/ReverseCommissionAction.php#L39) divides the full refund by each item's gross and does not cap reversal to the remaining vendor/platform amounts.

**Impact:** vendor balances and commission receivables can be over-reversed. The first commission can also have its state advanced twice even where the ledger idempotency key suppresses a duplicate ledger group.

**Required regression:** two items with different totals, partial and full refunds, retry of the event, and assertions that each reversal is capped and aggregate debits equal the refunded allocation exactly once.

### VTSE-005 — P1 — Completed refund can call the gateway again

**Trigger:** an API refund completes successfully, then a `REFUND` webhook or queue retry processes the same refund.

**Evidence:** [ProcessRefundAction.php](../../app/Modules/Payments/Application/Actions/ProcessRefundAction.php#L41) locks the refund but has no completed-state guard before marking it processing and calling the gateway; [ProcessPaymobWebhookAction.php](../../app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php#L78) can enter that path.

**Impact:** duplicate gateway refund attempt or a valid completed refund being overwritten as failed.

**Required regression:** replay a completed refund and assert one gateway call, one ledger group, and a permanently completed state.

### VTSE-006 — P1 — Payment initiation lacks booking-state and prior-payment guards

**Trigger:** the owner initiates payment for a draft, cancelled, empty, already paid, or already pending booking.

**Evidence:** [InitiatePaymentController.php](../../app/Modules/Payments/Http/Controllers/Customer/InitiatePaymentController.php#L28) checks ownership; [InitiatePaymentAction.php](../../app/Modules/Payments/Application/Actions/InitiatePaymentAction.php#L22) immediately calls the gateway and creates a payment without checking booking lifecycle/payment state or an existing payable attempt.

**Impact:** invalid-state payments and duplicate gateway intents.

**Required regression:** table-driven tests for draft, cancelled, confirmed-unpaid, confirmed-paid, empty, and duplicate-pending bookings.

### VTSE-007 — P1 — Captured webhook amount is not reconciled with the payment

**Trigger:** Paymob sends a successful capture event with an amount or currency different from the local payment.

**Evidence:** [ProcessPaymobWebhookAction.php](../../app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php#L102) invokes capture without validating the parsed captured amount; [CapturePaymentAction.php](../../app/Modules/Payments/Application/Actions/CapturePaymentAction.php#L46) posts the full local payment amount.

**Impact:** the system can mark and ledger more money than the gateway actually captured.

**Required regression:** mismatched amount/currency must fail closed, preserve the payment state, and create no capture ledger.

### VTSE-008 — P1 — Duplicate PaymentCaptured delivery can inflate amount paid

**Trigger:** a queued listener succeeds in updating the booking then is retried, or the same event is delivered twice.

**Evidence:** [UpdateBookingPaymentStatusListener.php](../../app/Modules/Booking/Application/Listeners/UpdateBookingPaymentStatusListener.php#L20) increments `amount_paid_minor` without an event or payment deduplication record.

**Impact:** the booking can appear overpaid and transition incorrectly.

**Required regression:** handle the same captured payment event twice and assert an unchanged amount and state on the second delivery.

### VTSE-009 — P1 — Two settlement paths can pay one withdrawal twice

**Trigger:** the weekly settlement batch and the admin mark-paid flow read the same approved withdrawal before either updates it.

**Evidence:** [SettlementRunAction.php](../../app/Modules/Settlement/Application/Actions/SettlementRunAction.php#L29) reads approved withdrawals without a row lock and uses `wd_settle_batch:{id}`; [MarkWithdrawalPaidAction.php](../../app/Modules/Settlement/Application/Actions/MarkWithdrawalPaidAction.php#L42) checks state before its transaction and uses `wd_settle:{id}` near line 100.

**Impact:** two valid ledger groups can represent the same payout. The batch path also marks paid without the bank proof, transfer reference, admin identity, and audit evidence required by the manual path.

**Required regression:** interleave both paths for one withdrawal and assert a single transition, ledger group, proof policy, and payout reference.

### VTSE-010 — P1 — Direct vendor modification is born expired

**Trigger:** vendor uses `POST /vendor/booking-vendors/{id}/modify`, then the customer tries to accept.

**Evidence:** [VendorModifyBookingAction.php](../../app/Modules/Booking/Application/Actions/VendorModifyBookingAction.php#L77) creates a pending modification without `expires_at`; [CustomerConfirmModifiedBookingAction.php](../../app/Modules/Booking/Application/Actions/CustomerConfirmModifiedBookingAction.php#L77) rejects null expiry as expired.

**Impact:** the API reports a created proposal that the customer can never accept.

**Required regression:** create through the vendor HTTP route, accept through the customer HTTP route before expiry, then reject after expiry.

### VTSE-011 — P1 — Vendor modification bypasses paid/cancelled booking guard

**Trigger:** vendor modifies a booking after payment or customer cancellation.

**Evidence:** [VendorModifyBookingAction.php](../../app/Modules/Booking/Application/Actions/VendorModifyBookingAction.php#L48) does not call `PreventModificationAfterPaymentAction` or validate the parent lifecycle before moving the booking to customer review.

**Impact:** a paid or cancelled booking can re-enter negotiation.

**Required regression:** paid, partially paid, refunded, cancelled, completed, and active-state cases through the HTTP route.

### VTSE-012 — P1 — Accepted add-item modification trusts vendor payload and misses required snapshots

**Trigger:** vendor proposes a newly added item and customer accepts it.

**Evidence:** [VendorModifyRequest.php](../../app/Modules/Booking/Http/Requests/VendorModifyRequest.php#L28) accepts broad item data; [AddBookingModificationItemAction.php](../../app/Modules/Booking/Application/Actions/AddBookingModificationItemAction.php#L110) lets payload product type override the service; [CustomerConfirmModifiedBookingAction.php](../../app/Modules/Booking/Application/Actions/CustomerConfirmModifiedBookingAction.php#L221) creates a booking item without proving service ownership/publication/price and omits required snapshot/status fields from the booking-item schema.

**Impact:** foreign or unpublished service, fabricated price/type, database failure, or incomplete financial/tax/commission snapshots.

**Required regression:** all three product types plus foreign, unpublished, changed-price, missing-field, and wrong-type cases.

### VTSE-013 — P1 — Modification preview computes the wrong quantity delta

**Trigger:** item quantity changes, with or without a unit-price change.

**Evidence:** [BookingModificationDiffService.php](../../app/Modules/Booking/Application/Services/BookingModificationDiffService.php#L79) calculates `(newPrice - oldPrice) * oldQuantity` instead of `newPrice * newQuantity - oldPrice * oldQuantity`.

**Impact:** customer approval is based on a displayed delta different from the applied booking total.

**Required regression:** old `1 × 100`, new `3 × 200`, expected delta `500`; include quantity-only and price-only cases.

### VTSE-014 — P1 — Removing an item through an accepted modification leaves inventory reserved

**Trigger:** customer accepts a proposal that removes a rental or sale item.

**Evidence:** [CustomerConfirmModifiedBookingAction.php](../../app/Modules/Booking/Application/Actions/CustomerConfirmModifiedBookingAction.php#L233) deletes the booking item without releasing or changing its `service_inventory_reservations` row.

**Impact:** phantom held/confirmed inventory blocks later customers.

**Required regression:** remove rental and sale items and assert the linked reservation is released in the same transaction.

### VTSE-015 — P1 — Vendor can decide a customer-cancelled booking

**Trigger:** customer cancels during vendor review, then vendor accepts, rejects, or modifies its still-pending allocation.

**Evidence:** [CancelBookingByCustomerAction.php](../../app/Modules/Booking/Application/Actions/CancelBookingByCustomerAction.php#L48) changes the booking but does not close vendor allocations; [VendorAcceptBookingAction.php](../../app/Modules/Booking/Application/Actions/VendorAcceptBookingAction.php#L40), [VendorRejectBookingAction.php](../../app/Modules/Booking/Application/Actions/VendorRejectBookingAction.php#L41), and [VendorModifyBookingAction.php](../../app/Modules/Booking/Application/Actions/VendorModifyBookingAction.php#L57) validate allocation state/deadline without rejecting the cancelled parent.

**Impact:** a cancelled booking can be mutated or potentially confirmed after cancellation.

**Required regression:** cancellation followed by each vendor decision; all must fail atomically and preserve cancellation.

### VTSE-016 — P1 — Unlimited sale stock is treated as zero

**Trigger:** add a sale service whose `stock_quantity` is null, where null represents unlimited stock.

**Evidence:** [AddItemToBookingAction.php](../../app/Modules/Booking/Application/Actions/AddItemToBookingAction.php#L170) converts null to zero before subtracting held quantity, while the catalog inventory path treats null as unlimited.

**Impact:** valid unlimited-stock products cannot be booked.

**Required regression:** null stock succeeds; finite stock still rejects only when the requested quantity exceeds availability.

### VTSE-017 — P1 — Service-create API accepts inactive or wrong-type categories

**Trigger:** approved vendor submits rental/sale/digital service creation with an existing category that is inactive or excludes that product type.

**Evidence:** [CreateRentalServiceRequest.php](../../app/Modules/Catalog/Http/Requests/CreateRentalServiceRequest.php#L46) validates category existence only; the corresponding sale and digital requests use the same pattern. The Filament selector filters categories, so UI and API enforce different rules.

**Impact:** invalid catalog classification and services that cannot be managed consistently.

**Required regression:** every product type against allowed, excluded, inactive, and missing categories.

### VTSE-018 — P1 — Legacy vendor fulfillment page bypasses fulfillment guards

**Trigger:** vendor advances an item from `VendorActiveBookingsPage`.

**Evidence:** [VendorActiveBookingsPage.php](../../app/Modules/Booking/Filament/Vendor/Pages/VendorActiveBookingsPage.php#L127) and line 178 call `MarkBookingItemStateAction` directly instead of guarded fulfillment Actions.

**Impact:** unpaid, cancelled, or refunded work may be marked complete; completion evidence and `BookingItemFulfilled` aggregation can be skipped.

**Required regression:** the page must reject unpaid/refunded/cancelled bookings and use the same evidence/event behavior as the API Actions.

### VTSE-019 — P1 — Item completion does not complete the parent booking

**Trigger:** every item reaches its per-type terminal fulfillment state.

**Evidence:** [AggregateBookingVendorSubStatusListener.php](../../app/Modules/Booking/Application/Listeners/AggregateBookingVendorSubStatusListener.php#L48) completes the vendor allocation and emits `BookingVendorCompleted`; no consumer of that event aggregates all vendors into `bookings.fulfillment_status` or invokes [CompleteBookingTransition.php](../../app/Modules/Booking/Domain/States/BookingLifecycleStatus/Transitions/CompleteBookingTransition.php#L20).

**Impact:** parent bookings can remain active/not-started, `BookingCompleted` does not fire, and downstream loyalty/review eligibility is stranded.

**Required regression:** multiple vendors and mixed product types; complete the last item and assert exactly one parent completion event and lifecycle transition.

### VTSE-020 — P2 — HTTP idempotency key is not scoped to actor and operation

**Evidence:** [IdempotencyKeyMiddleware.php](../../app/Modules/Payments/Http/Middleware/IdempotencyKeyMiddleware.php#L25) looks up the supplied key without a complete actor/route scope and does not atomically reserve it before downstream execution.

**Impact:** a key reused by another actor/route can return an unrelated response; concurrent first requests can both reach the gateway.

**Required regression:** same key across users/routes and two concurrent requests; only the matching actor/operation may replay.

### VTSE-021 — P2 — Ledger idempotency accepts a different financial payload

**Evidence:** [PostLedgerTransactionAction.php](../../app/Modules/Settlement/Application/Actions/PostLedgerTransactionAction.php#L236) compares transaction kind and currency only when reusing a key, not accounts, directions, amounts, or related entity.

**Impact:** accidental reuse can silently return a ledger group for different money movements.

**Required regression:** same key/kind/currency with changed entries must throw a payload-mismatch exception.

### VTSE-022 — P2 — Customer modification decision lacks operation idempotency

**Evidence:** [Booking customer routes](../../app/Modules/Booking/Routes/customer.php#L30) do not apply idempotency middleware to the decision endpoint. Static checks inside the Action do not provide an externally scoped idempotency result for concurrent duplicate submissions.

**Impact:** duplicate decisions can race and repeat transitions/events.

**Required regression:** concurrent accept and accept/reject conflict with one persisted decision and one event chain.

### VTSE-023 — P2 — Paymob failure detail is discarded

**Evidence:** [PaymobGateway.php](../../app/Modules/Payments/Infrastructure/Gateways/PaymobGateway.php#L113) parses a failure code, but [ProcessPaymobWebhookAction.php](../../app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php#L119) uses the previous payment failure value instead of the webhook DTO value.

**Impact:** failures collapse to `unknown`, reducing support and reconciliation evidence.

**Required regression:** known gateway failure code maps to the expected persisted bilingual failure reason.

### VTSE-024 — P2 — Vendor category endpoint ignores vendor product approvals

**Evidence:** [VendorCategoryController.php](../../app/Modules/Catalog/Http/Controllers/Vendor/VendorCategoryController.php#L21) calls the public category list Action rather than the vendor-scoped Action.

**Impact:** vendors see active categories for product types they are not approved to sell, producing inconsistent UI/API choices.

**Required regression:** a rental-only vendor must not receive sale-only/digital-only categories.

### VTSE-025 — P2 — Settlement-run history is disconnected from the settlement command

**Evidence:** [SettlementRunAction.php](../../app/Modules/Settlement/Application/Actions/SettlementRunAction.php#L27) processes withdrawals and returns counters but never creates or updates [SettlementRun.php](../../app/Modules/Settlement/Domain/Models/SettlementRun.php#L13). Repository references show settlement-run records created only by development seeding/factories and displayed by Filament.

**Impact:** the weekly command can pay withdrawals while the admin settlement-run screen has no corresponding period, totals, status, or audit record.

**Required regression:** one command run must create one run record with bounded period, totals, final status, and traceable included withdrawals.

## Original product decisions (now resolved above)

### VTSE-D01 — Suspended customer access

`EnsureAccountIsActive` is not consistently applied to Booking, Payments, Reviews, and Settlement customer route groups. Decide whether suspended customers may read existing orders while being blocked from new booking, payment, review, and wallet mutations. Then enforce and test that matrix.

### VTSE-D02 — Payment endpoint roles

Payments customer routes require authentication but not `role:customer`, unlike neighboring customer modules. Decide whether admins/vendors may intentionally initiate or view customer payments through these endpoints. Keep ownership checks mandatory regardless of that decision.

## Original verification gaps

1. No HTTP test covers vendor service creation, resubmission ownership, middleware, or payload allowlisting.
2. No single API test covers customer draft → add item → submit → vendor accept/modify → customer decision.
3. No test links capture → commission → refund/fulfillment → parent completion → wallet → withdrawal → settlement.
4. No Browser/UAT suite signs in as separate vendor and customer identities; current Playwright coverage is admin-only.
5. No concurrency acceptance proves payment capture, refund, modification decision, ledger posting, or withdrawal settlement exactly once.

## Original fix order

1. **Security boundary:** VTSE-001 and VTSE-002.
2. **Money correctness:** VTSE-003 through VTSE-009, VTSE-020, and VTSE-021.
3. **Booking state and inventory:** VTSE-010 through VTSE-019 and VTSE-022.
4. **Operational auditability:** VTSE-023 through VTSE-025.
5. Resolve VTSE-D01/D02, then add the multi-role acceptance journey.

Each fix should start with the smallest failing regression test. Money and state transitions need retry/idempotency cases, and cross-account resources should return 404 where the project uses non-enumerating ownership semantics.

## Required end-to-end acceptance journey

The eventual closure test must use separate vendor, customer, admin, finance, and support identities and must cover:

1. Vendor registration, documents, approval, and product-type grant.
2. Rental, sale, and digital service creation through HTTP, moderation, change request, safe resubmission, and publish.
3. Customer draft, item add, inventory hold, submit, vendor decision, customer modification decision, and confirmation.
4. Payment initiation, verified gateway capture, duplicate-event replay, and balanced capture/commission ledger.
5. Guarded fulfillment with required evidence for every product type, vendor aggregation, and parent booking completion exactly once.
6. Review/loyalty eligibility only after completion.
7. Partial and full multi-item refunds with exact commission reversal.
8. Vendor wallet visibility, withdrawal request, approval, one payout path, settlement-run history, and reconciliation.
9. Cross-role/ownership denial, Arabic and English API/UI labels, public ULIDs, audit timeline, and no internal identifiers.
10. Browser proof for the user-visible admin/vendor/customer checkpoints plus HTTP assertions for callbacks, concurrency, and ledger invariants.

The journey must run only against disposable local data until these findings are fixed. A passing admin page-render suite does not close the business journey.
