# Risk-based QA execution plan

Scope: exactly 20 original catalog IDs selected; separate known-defect controls and exploratory candidates do not inflate that count. Product source, original catalog and previous reports remain unchanged.

## Source reconciliation and authority

17 real Laravel modules plus Firebase external integration explain the 18 CSV classifications. The saved workbook has exactly the same 1,292 scenario IDs as the JSON and prior execution CSV. There is no missing eighteenth Laravel module.

Original priorities: 61 P0, 639 P1, 592 P2. Prior execution: 1,258 not-run, 31 needs-business-confirmation, 3 partially-tested. None is a verified full-scenario pass. There are 690 blank preconditions and 368 blank affected-entity fields; all related-scenario fields are blank. A derived shared-flow link is not an original relationship.

All discovered code-derived scenarios retain their original expected outcomes and lack an authoritative PRD. AGENTS/engineering policy provides ownership, UTC, integer-money, atomic/append-only financial history and idempotency invariants. Source behavior is verified only within that stated boundary; uncertain policy is NEEDS_BUSINESS_DECISION. FR-2 mandatory end versus WIZ-01 optional end remains unresolved.

The matrix ranks every scenario by original severity first, then financial/lifecycle/security/retry/propagation exposure. This is a transparent ordinal prioritization, not a calibrated probability or safety score. Unselected P0 areas remain NOT_TESTED. Layer mapping is a design proposal, not executable coverage. No assumption that all scenarios require a browser.

## Execution and isolation

Browser mutations use real HTTP against the existing guarded disposable SQLite launcher and tester.army browser readback. Financial integration uses an isolated socket-only MySQL/MariaDB instance, real routing, idempotency, capture/refund processor, ledger, committed rows and real after-commit listeners. Only the external gateway boundary is simulated. No production credentials or gateway calls. File-cache wallet locks are real, but do not prove production Redis behavior.

The first MySQL run exposes a real migration incompatibility; preserve its failed trace. MariaDB is a separately labeled compatibility attempt, not MySQL acceptance. DatabaseTruncation isolates committed test data without reversing migrations against populated append-only history. Shutdown only the private database daemon; retain guarded artifacts for review.

No automatic retries. Correct locator/data/harness errors only; keep original business oracles. Re-run only affected tests after those corrections. Confirmed failing repros stay outside ordinary CI. Stop after the selected pilot; do not rerun the full suites.

## Selected test designs

### SC-Booking-d23fdddb0e — Create an event draft

- Original priority: P1; roles: customer; source: `app/Modules/Booking/Application/Actions/CreateBookingDraftAction.php:17`.
- Test: `tests/tester-army/risk/booking.e2e.ts` → `connected sale booking`.
- Preconditions/data: Verified isolated customer, approved vendor, published sale service 12500 EGP minor units, stock20, same-currency future event and valid local address; each standalone guard gets its own draft.
- Actions: Create the booking through BookingRepository inside a transaction, append the optional customer note, and emit BookingDraftCreated after commit. See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): A draft event booking is available for cart items.
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Booking.
- Cross-module effects: Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Booking-159b4bcd53 — Add a service to an event

- Original priority: P1; roles: customer; source: `app/Modules/Booking/Application/Actions/AddItemToBookingAction.php:34`.
- Test: `tests/tester-army/risk/booking.e2e.ts` → `connected sale booking`.
- Preconditions/data: Verified isolated customer, approved vendor, published sale service 12500 EGP minor units, stock20, same-currency future event and valid local address; each standalone guard gets its own draft.
- Actions: Check owning customer, draft lifecycle, published service and matching currency. Snapshot transport and prices, resolve effective rental duration, lock service inventory, reserve rental/sale capacity, create the vendor group and item, and emit BookingItemAdded after commit. See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): The event contains the requested service and its commercial snapshot; rental/sale inventory is held for the cart.
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Booking; BookingItem; BookingVendor.
- Cross-module effects: Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Booking-96dc5a0c0e — Submit an event for vendor review

- Original priority: P1; roles: customer; source: `app/Modules/Booking/Application/Actions/SubmitBookingAction.php:27`.
- Test: `tests/tester-army/risk/booking.e2e.ts` → `connected sale booking`.
- Preconditions/data: Verified isolated customer, approved vendor, published sale service 12500 EGP minor units, stock20, same-currency future event and valid local address; each standalone guard gets its own draft.
- Actions: Replay a matching unexpired idempotency record or lock the draft. Require ownership, draft lifecycle, at least one item and coverage minimums. Snapshot item VAT, enter vendor_review, append a transition, reset vendor sub-status to pending and assign a common 24-hour deadline. Emit BookingSubmittedToVendor and cache the response after commit. See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): The event awaits vendor decisions with VAT snapshots and deadlines. A matching retry returns the previously submitted booking.
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Booking; BookingVendor; StateTransition.
- Cross-module effects: Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Booking-ff6a2c0c84 — Matching idempotency retry

- Original priority: P1; roles: customer; source: `app/Modules/Booking/Application/Actions/SubmitBookingAction.php:29`.
- Test: `tests/tester-army/risk/booking.e2e.ts` → `connected sale booking`.
- Preconditions/data: Verified isolated customer, approved vendor, published sale service 12500 EGP minor units, stock20, same-currency future event and valid local address; each standalone guard gets its own draft.
- Actions: Unexpired key matches canonical customer, booking and request-content hash. Return cached booking without resubmitting vendors. See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Return cached booking without resubmitting vendors.
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Booking; BookingVendor; StateTransition.
- Cross-module effects: Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Booking-1806e15c31 — Final vendor acceptance

- Original priority: P1; roles: vendor; source: `app/Modules/Booking/Application/Actions/VendorAcceptBookingAction.php:83`.
- Test: `tests/tester-army/risk/booking.e2e.ts` → `connected sale booking`.
- Preconditions/data: Verified isolated customer, approved vendor, published sale service 12500 EGP minor units, stock20, same-currency future event and valid local address; each standalone guard gets its own draft.
- Actions: No nonaccepted contribution remains. Set parent confirmed and emit BookingConfirmed after commit. See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Set parent confirmed and emit BookingConfirmed after commit.
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Booking; BookingVendor; StateTransition.
- Cross-module effects: Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Booking-32b824ae4d — Submit Booking guard

- Original priority: P1; roles: customer; source: `app/Modules/Booking/Application/Actions/SubmitBookingAction.php:40`.
- Test: `tests/tester-army/risk/booking.e2e.ts` → `SC-Booking-32b824ae4d`.
- Preconditions/data: Verified isolated customer, approved vendor, published sale service 12500 EGP minor units, stock20, same-currency future event and valid local address; each standalone guard gets its own draft.
- Actions: Reject when the condition holds during submit booking. The exact source predicate is retained in the branch. See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): HTTP_NOT_FOUND response
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Booking; BookingVendor; StateTransition.
- Cross-module effects: Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Booking-e8e7c96608 — Booking has no items

- Original priority: P1; roles: customer; source: `app/Modules/Booking/Application/Actions/SubmitBookingAction.php:56`.
- Test: `tests/tester-army/risk/booking.e2e.ts` → `SC-Booking-e8e7c96608`.
- Preconditions/data: Verified isolated customer, approved vendor, published sale service 12500 EGP minor units, stock20, same-currency future event and valid local address; each standalone guard gets its own draft.
- Actions: Reject when the condition holds during submit booking. The exact source predicate is retained in the branch. See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): HTTP_UNPROCESSABLE_ENTITY response
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Booking; BookingVendor; StateTransition.
- Cross-module effects: Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Payments-3436fcba08 — Success amount mismatch

- Original priority: P0; roles: Paymob; source: `app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php::execute`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Payments_3436fcba08_mismatch_amount_currency_and_missing_amount`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Captured amount missing or amount/currency differs See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Log mismatch and leave payment unchanged
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: GatewayWebhookLog; Payment; Refund; IdempotencyKey.
- Cross-module effects: Payments; Settlement; Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Payments-db31a76816 — Successful capture

- Original priority: P0; roles: Paymob; source: `app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php::execute`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Payments_db31a76816_capture_commits_booking_ledger_and_idempotency`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Signed success with matching amount/currency and resolvable payment See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Capture action invoked; capture idempotency recorded
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: GatewayWebhookLog; Payment; Refund; IdempotencyKey.
- Cross-module effects: Payments; Settlement; Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Payments-db566d3009 — Repeated callback

- Original priority: P0; roles: Paymob; source: `app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php::execute`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Payments_db566d3009_duplicate_callback_has_no_financial_or_event_effect`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Deterministic order/transaction/type/success key already processed See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Duplicate log; processing skipped
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: GatewayWebhookLog; Payment; Refund; IdempotencyKey.
- Cross-module effects: Payments; Settlement; Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Payments-199cb154e1 — Invalid signature

- Original priority: P0; roles: Paymob; source: `app/Modules/Payments/Application/Actions/ProcessPaymobWebhookAction.php::execute`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Payments_199cb154e1_invalid_signature_rejected_before_capture`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Gateway signature verification fails See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Log invalid_signature then reject 401
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: GatewayWebhookLog; Payment; Refund; IdempotencyKey.
- Cross-module effects: Payments; Settlement; Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Payments-83d4a64c4f — Other payer or unknown id

- Original priority: P0; roles: customer; source: `app/Modules/Payments/Http/Controllers/Customer/ShowPaymentController.php::__invoke`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Payments_83d4a64c4f_other_customer_payment_not_disclosed`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: No payment matches both public id and user id See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): 404; no disclosure
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Payment.
- Cross-module effects: Payments. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Payments-e7d1c7c10f — Gateway success

- Original priority: P0; roles: system; source: `app/Modules/Payments/Application/Actions/ProcessRefundAction.php::execute`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Payments_e7d1c7c10f_full_refund_commits_reversal_and_booking_projection`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Gateway result success=true See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Completed refund, refunded Payment, refund ledger and after-commit event
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Refund; Payment; LedgerTransactionGroup.
- Cross-module effects: Payments; Settlement; Booking; Loyalty. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Payments-ecf87b2d24 — Already completed

- Original priority: P0; roles: system; source: `app/Modules/Payments/Application/Actions/ProcessRefundAction.php::execute`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Payments_ecf87b2d24_completed_refund_replay_is_a_noop`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Refund status completed See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Return without second gateway refund
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Refund; Payment; LedgerTransactionGroup.
- Cross-module effects: Payments; Settlement; Booking; Loyalty. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Settlement-f0a715e615 — Balanced new transaction

- Original priority: P0; roles: internal; source: `app/Modules/Settlement/Application/Actions/PostLedgerTransactionAction.php::post`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Settlement_f0a715e615_balanced_transaction_is_atomic_and_projected`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Wallet currencies equal and debit totals=credit totals See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Atomic group, entries, projections and after-commit event
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Wallet; LedgerTransactionGroup; WalletLedgerEntry.
- Cross-module effects: Settlement. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Settlement-b02f55e5d5 — Exact replay

- Original priority: P0; roles: internal; source: `app/Modules/Settlement/Application/Actions/PostLedgerTransactionAction.php::post`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Settlement_b02f55e5d5_exact_replay_preserves_immutable_rows`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Existing key and exact compared payload See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Existing group returned; no new entries
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Wallet; LedgerTransactionGroup; WalletLedgerEntry.
- Cross-module effects: Settlement. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Settlement-25a3403605 — Different replay payload

- Original priority: P0; roles: internal; source: `app/Modules/Settlement/Application/Actions/PostLedgerTransactionAction.php::post`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Settlement_25a3403605_changed_replay_rejected_without_side_effect`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Existing key with differing compared payload See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): DuplicateIdempotencyKeyWithDifferentPayloadException
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Wallet; LedgerTransactionGroup; WalletLedgerEntry.
- Cross-module effects: Settlement. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Settlement-71a4330a50 — Unbalanced entries

- Original priority: P1; roles: internal; source: `app/Modules/Settlement/Application/Actions/PostLedgerTransactionAction.php::post`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Settlement_71a4330a50_unbalanced_transaction_creates_no_posting`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Total debit != total credit See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): UnbalancedTransactionException
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Wallet; LedgerTransactionGroup; WalletLedgerEntry.
- Cross-module effects: Settlement. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Settlement-fc80457948 — Currency mismatch

- Original priority: P1; roles: internal; source: `app/Modules/Settlement/Application/Actions/PostLedgerTransactionAction.php::post`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Settlement_fc80457948_currency_mismatch_preserves_existing_projection`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Any wallet currency differs See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): WalletCurrencyMismatchException
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Wallet; LedgerTransactionGroup; WalletLedgerEntry.
- Cross-module effects: Settlement. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

### SC-Booking-320197067b — Finite sale stock under concurrency

- Original priority: P1; roles: customer; source: `app/Modules/Booking/Application/Actions/AddItemToBookingAction.php:180`.
- Test: `qa-audit/risk-program/tests/FinancialPilotTest.php` → `test_SC_Booking_320197067b_two_processes_cannot_oversell_one_unit`.
- Preconditions/data: Socket-only disposable MySQL-compatible database; fresh truncated data, approved-role factories. Capture/refund fixture: confirmed unpaid booking and pending 10000 EGP payment; ledger fixture: equal 10000 debit/credit. Concurrency: stock1, two different owned drafts and two independent PHP processes held behind a real service row lock.
- Actions: Sale stock is finite; competing add operations acquire the service row lock. Count blocking reservations, then reject insufficient remaining quantity with 409. See exact request bodies/process barrier in the linked test; no invented endpoint for Action-only invariants.
- Expected outcome (unchanged catalog): Count blocking reservations, then reject insufficient remaining quantity with 409.
- Exact assertions: the test pins HTTP/error class, lifecycle/ownership and integer amount/group/count as relevant; named assertions and actual/expected values are retained in JSON and JUnit/trace evidence. Browser navigation alone never advances full coverage.
- Persistence: real read-only SQLite snapshots for browser/API flow; real committed MySQL-compatible rows, ledger sums, immutable row snapshots and wallet projections for financial cases. Entities: Booking; BookingItem; BookingVendor.
- Cross-module effects: Booking. Financial tests observe real PaymentCaptured, RefundCompleted and LedgerTransactionPosted events and booking projections; booking replay compares persisted transitions/deadlines/notification counts. Uninstrumented downstream systems remain a limitation.
- Failure/cleanup: retain evidence before decisive assertion; failed business assertion stays failing. Disposable database/app shuts down; no shared data repair. Setup/SQL/tool errors are BLOCKED or PARTIALLY_TESTED, not business passes.
- Limits: this is a specified branch/fixture; variants, all roles/locales and real provider signatures are not implied. Final coverage classification is reconciled from executed evidence.

## Separate exploratory work

Focused tester.army charters investigate stale planner/cart state and wishlist/account ownership/localization edges not exercised by deterministic booking/financial tests. Each uses its own disposable app, sequentially because global fixture manifests are shared. Findings are candidates until an independent deterministic test asserts approved behavior and fails for that reason. Preserve warnings and rejected/environment findings separately.

## Status policy and review

VERIFIED_PASS requires every decisive stated business assertion in the tested scope and reproducible evidence. VERIFIED_FAIL requires a demonstrated intended-outcome contradiction. PARTIALLY_TESTED records useful branch evidence with unproven portions/tool interruptions. BLOCKED means prerequisites prevent reaching the oracle. NEEDS_BUSINESS_DECISION retains genuine policy ambiguity. NOT_TESTED means no exact current execution.

Coverage is tracked per original ID, not per passing test title, page visited or scenario name embedded in a test. The original CSV is preserved. Known chat report and wishlist availability controls are reported separately where the original scenario outcome does not contain their exact contract oracle.
