# GitHub #85 implementation evidence

The captured issue in `.e2e/risk-program/remediation/github-open-issues.json` reports a vendor allocation that remains Pending after its response deadline. The `Mark Preparing` control on an unaccepted booking is an additional current-source finding from the follow-up investigation, not part of that issue body's stated reproduction.

## Source finding

`ExpireStaleVendorReviewsAction::execute` selects VendorReview bookings with a pending allocation whose response deadline is at or before the evaluation time. It locks the booking and affected allocations, marks overdue allocations TimedOut, writes state-transition and audit rows, and advances the booking to CustomerReview after its final pending allocation expires. An event-start boundary cancels the booking. `routes/console.php` registers `booking:expire-stale-vendor-reviews` every minute, and `BookingServiceProvider` registers the command.

The command checks `booking.vendor_review_expiry.enabled` before invoking the action. `config/booking.php` defaults that flag to enabled, while `BOOKING_VENDOR_REVIEW_EXPIRY_ENABLED=false` disables the command. The repository tests verify action behavior, the disabled-flag behavior, and the schedule declaration; they do not establish the flag value or scheduler process state in the environment from the issue report.

The confirmed application gap was independent of scheduler execution: fulfillment lane visibility depended on item type/status, and the shared action guard did not require an accepted vendor allocation. That let Pending or TimedOut allocations expose and invoke fulfillment.

## Change

`FulfillmentGuards` now permits state transitions only when the allocation is Accepted or already InProgress. `VendorBookingDetailPage` uses the same eligible statuses before rendering any fulfillment lane action. The existing `BookingNotEligibleForFulfillmentException` carries the allocation-ineligible reason; the vendor transition controller already maps that exception to HTTP 409. Reporting a fulfillment issue retains its existing guards and remains separate from state transitions.

## Verification

- Before the fix, the new direct-action regression failed because no eligibility exception was thrown for a Pending allocation with an expired deadline.
- Before the fix, the vendor detail page regression failed because `Mark Preparing` appeared for a TimedOut allocation.
- `php vendor/bin/pest tests/Feature/StaleVendorReviewExpiryTest.php tests/Feature/VendorFulfillmentAllocationEligibilityTest.php tests/Feature/VendorBookingDetailAuthorizationTest.php --bail` — 17 tests, 68 assertions passed. Coverage includes Pending-after-deadline and TimedOut rejection, Accepted/InProgress fulfillment, hidden page controls, exact-deadline expiry, and the scheduler declaration.
- `php vendor/bin/pint --test` on the six changed PHP files — passed.

## Remaining environment evidence

The affected environment's `BOOKING_VENDOR_REVIEW_EXPIRY_ENABLED` value and scheduler execution have not been inspected. Confirm those there before concluding why the allocation stayed Pending or marking the reported lifecycle behavior fully accepted. The code change prevents fulfillment for an allocation that is still Pending or has become TimedOut; it cannot prove that the scheduled expiry command ran.
