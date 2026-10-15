# Customer discovery verification

Issue #141 was closed at the owner's request after local acceptance on `codex/qa-verify-flows`, base/main `4b086b58a23cf122bfb5667e9aacce0137665899`.

The customer can ask the selected vendor about a published service before booking. The form explains that replies appear in the account, and both form and conversation retain a service link. Inquiry access is scoped to its customer and owning vendor; account contact fields are not projected into the conversation.

Fresh `php vendor/bin/pest tests/Feature/QaVerifyCustomerDiscoveryTest.php tests/Feature/VendorPrebookingInquiryTest.php --bail` passed 26 tests / 164 assertions in 18.24 seconds, including EN/AR rendering, access restrictions and contact-field privacy. Log: `storage/logs/qa-inquiry-closure-check.txt`.

The inquiry case in `tests/e2e/qa-verify-customer-discovery.spec.ts` passed locally in Chromium: customer question, owning vendor inbox, vendor reply, and customer reload displaying that reply (13.2 seconds). Log: `storage/logs/qa-discovery-second.txt`. The other availability case in that run failed; #145 remains open.

Batch changes remain uncommitted/unpushed, and final repository-wide gates are pending. This is local acceptance using disposable fixtures; production and external provider delivery are unverified.
